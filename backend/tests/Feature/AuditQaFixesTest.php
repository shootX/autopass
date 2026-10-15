<?php

use App\Jobs\SendSms;
use App\Models\TbcPayment;
use App\Models\User;
use App\Services\Payments\SettleTbcPayment;
use App\Services\Security\RetireDefaultPasswords;
use App\Services\Sms\MockSmsGateway;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tymon\JWTAuth\Facades\JWTAuth;

beforeEach(function () {
    DB::statement('PRAGMA defer_foreign_keys = ON');
    Http::fake(['*' => Http::response(['Success' => true])]);
    Queue::fakeExcept([SendSms::class]); // auth SMS still runs (sync) so codes reach the mock gateway
    $this->withoutMiddleware(PreventRequestForgery::class);
    $this->withoutMiddleware(ThrottleRequests::class);
    app(MockSmsGateway::class)->reset();
});

function qaReset(): void
{
    foreach (['tymon.jwt', 'tymon.jwt.auth'] as $id) {
        app()->forgetInstance($id);
    }
    JWTAuth::clearResolvedInstances();
    app('auth')->forgetGuards();
}

function qaPackage(string $suffix, array $extra = []): array
{
    $mgr = makeUser('5556500'.$suffix.'1', User::ROLE_MANAGER);
    $client = makeUser('5556500'.$suffix.'2');
    $wash = washFor($mgr);
    $car = row('user_cars', ['user_id' => $client->id, 'model_id' => 1, 'plate' => 'QA'.$suffix.'00QA']);
    $pkg = userPackage($client->id, $car, $extra + ['qr_code' => 'QAQR'.$suffix.'000000000']);

    return [$mgr, $client, $wash, $car, $pkg];
}

// D1
it('locks client accounts on a published default password too', function () {
    $c = makeUser('555650001');
    $c->forceFill(['password' => Hash::make('client123')])->save();
    app(RetireDefaultPasswords::class)->handle();
    expect((bool) $c->fresh()->password_reset_required)->toBeTrue();
    $this->postJson('/api/login', ['phone' => '555650001', 'password' => 'client123'])->assertStatus(401);
    expect(file_exists(database_path('migrations/2026_10_15_000003_retire_default_client_passwords.php')))->toBeTrue();
});

// D2
it('consumes an approve token once even when a parallel request holds it', function () {
    [$mgr, , , , $pkg] = qaPackage('1');
    $t = JWTAuth::fromUser($mgr);
    $token = $this->withToken($t)->getJson('/api/checkqr/QAQR1000000000')->json('approve_token');
    $key = 'package-approve:'.hash('sha256', $token);

    // A parallel request is mid-consume (holds the per-token lock): this one must not also spend it.
    $lock = Cache::lock($key.':lock', 10);
    expect($lock->get())->toBeTrue();
    $this->withToken($t)->postJson("/api/package/{$pkg}/approve", ['approve_token' => $token])->assertStatus(409);
    $lock->release();

    $this->withToken($t)->postJson("/api/package/{$pkg}/approve", ['approve_token' => $token])->assertOk();
    $this->withToken($t)->postJson("/api/package/{$pkg}/approve", ['approve_token' => $token])->assertForbidden();
    expect((int) DB::table('user_packages')->find($pkg)->used_washes)->toBe(1);
});

it('deducts once when the same package is approved twice in a row with one token', function () {
    [$mgr, , , , $pkg] = qaPackage('2');
    $t = JWTAuth::fromUser($mgr);
    $token = $this->withToken($t)->getJson('/api/checkqr/QAQR2000000000')->json('approve_token');
    $codes = [];
    foreach (range(1, 8) as $i) {
        $codes[] = $this->withToken($t)->postJson("/api/package/{$pkg}/approve", ['approve_token' => $token])->status();
    }
    expect(array_count_values($codes)[200] ?? 0)->toBe(1)->and((int) DB::table('user_packages')->find($pkg)->used_washes)->toBe(1);
});

// D3
it('answers 422, not 500, to array parameters', function () {
    [$mgr, , , , $pkg] = qaPackage('3');
    $t = JWTAuth::fromUser($mgr);
    $this->withToken($t)->postJson("/api/package/{$pkg}/approve", ['qr_code' => ['x']])->assertStatus(422);
    $this->withToken($t)->postJson("/api/package/{$pkg}/approve", ['approve_token' => ['x']])->assertStatus(422);
    qaReset();
    $admin = makeUser('555650301', User::ROLE_ADMIN);
    $this->withToken(JWTAuth::fromUser($admin))->getJson('/api/testpay?amount[]=5')->assertStatus(422);
});

// D4
// Superseded in QA round 2 (D13): a booking no longer allows bare-id approvals at all.
it('does not let a booking drain the package by bare id', function () {
    [$mgr, $client, $wash, $car, $pkg] = qaPackage('4');
    row('appointments', ['user_id' => $client->id, 'car_wash_id' => $wash, 'car_id' => $car, 'date' => now()->toDateString(), 'time' => '10:00', 'qr_code' => 'A'.uniqid()]);
    $t = JWTAuth::fromUser($mgr);
    $this->withToken($t)->postJson("/api/package/{$pkg}/approve")->assertForbidden();
    $this->withToken($t)->postJson("/api/package/{$pkg}/approve", ['approve_token' => approveTokenFor($mgr, $pkg)])->assertOk();
    $this->withToken($t)->postJson("/api/package/{$pkg}/approve")->assertForbidden();
    expect((int) DB::table('user_packages')->find($pkg)->used_washes)->toBe(1);
});

// D5
it('caps test payments per user per day', function () {
    config(['security.test_payment_max_amount' => 100, 'security.test_payment_daily_max' => 150]);
    $admin = makeUser('555650501', User::ROLE_ADMIN);
    $t = JWTAuth::fromUser($admin);
    $this->withToken($t)->getJson('/api/testpay?amount=100')->assertOk();
    $this->withToken($t)->getJson('/api/testpay?amount=60')->assertStatus(422);
    $this->withToken($t)->getJson('/api/testpay?amount=50')->assertOk();
    $this->travel(1)->days();
    $t = JWTAuth::fromUser($admin->fresh()); // the first token's TTL is one day
    $this->withToken($t)->getJson('/api/testpay?amount=100')->assertOk();
});

it('does not let sandbox points buy real vouchers or tickets', function () {
    $u = makeUser('555650502', User::ROLE_ADMIN);
    $u->forceFill(['points' => 30])->save();
    $p = TbcPayment::query()->create([
        'merchant_payment_id' => 'TQA00000000000001', 'user_id' => $u->id, 'type' => 'points', 'amount' => 100,
        'currency' => 'GEL', 'status' => 'pending', 'test_mode' => true, 'payload' => ['points' => 100],
    ]);
    app(SettleTbcPayment::class)->grantNow($p);
    $u->refresh();
    expect((int) $u->points)->toBe(130)->and((int) $u->test_points)->toBe(100);

    $v = s2Voucher(50);
    $this->withToken(JWTAuth::fromUser($u))->postJson('/api/vouchers/buy', ['voucher_id' => $v])->assertJson(['success' => false]);
    expect((int) $u->fresh()->points)->toBe(130);
    $cheap = s2Voucher(30);
    qaReset();
    $this->withToken(JWTAuth::fromUser($u))->postJson('/api/vouchers/buy', ['voucher_id' => $cheap])->assertJson(['success' => true]);
    expect((int) $u->fresh()->points)->toBe(100)->and((int) $u->fresh()->test_points)->toBe(100);

    $ticket = s2Ticket(10);
    qaReset();
    $this->withToken(JWTAuth::fromUser($u))->postJson('/api/tickets/buy', ['ticket_id' => $ticket, 'qty' => 1])->assertJson(['success' => false]);
});

// D6
it('refuses a refresh racing another refresh of the same token, and caps the session lifetime', function () {
    $u = makeUser('555650601');
    $old = JWTAuth::fromUser($u);
    $jti = JWTAuth::setToken($old)->getPayload()->get('jti');
    qaReset();
    $lock = Cache::lock('jwt-refresh:'.$jti, 10);
    expect($lock->get())->toBeTrue();
    $this->withToken($old)->postJson('/api/refresh')->assertStatus(409);
    $lock->release();
    qaReset();

    config(['security.session_max_minutes' => 60]);
    $new = $this->withToken($old)->postJson('/api/refresh')->assertOk()->json('access_token');
    $this->travel(61)->minutes();
    qaReset();
    config(['jwt.ttl' => 1000]);
    $this->withToken($new)->postJson('/api/refresh')->assertStatus(401);
});

// D7
it('does comparable hashing work on the decoy register path', function () {
    makeUser('555650701');
    Hash::spy();
    $this->postJson('/api/register', ['phone' => '555650701'])->assertOk();
    Hash::shouldHaveReceived('make')->atLeast()->once();
});

// D8
it('does not reveal on change_phone whether a number is taken', function () {
    makeUser('555650801');
    $me = makeUser('555650802');
    $t = JWTAuth::fromUser($me);
    $taken = $this->withToken($t)->postJson('/api/change_phone', ['phone' => '555650801']);
    $free = $this->withToken($t)->postJson('/api/change_phone', ['phone' => '555650803']);
    expect($taken->status())->toBe($free->status())->toBe(200)
        ->and(array_keys($taken->json()))->toEqualCanonicalizing(array_keys($free->json()));
    expect(count(app(MockSmsGateway::class)->messages))->toBe(1); // nothing sent to the taken number
});

// D9
it('does not audit failed admin writes and audits admin testpay', function () {
    $admin = makeUser('555650901', User::ROLE_ADMIN);
    $this->actingAs($admin)->from('/dashboard')->post('/dashboard/corporate/store', [])->assertRedirect();
    expect(DB::table('admin_audit_logs')->where('action', 'admin.corporate.store')->exists())->toBeFalse();
    auth()->logout();
    $this->withToken(JWTAuth::fromUser($admin))->getJson('/api/testpay?amount=5')->assertOk();
    $row = DB::table('admin_audit_logs')->where('action', 'testpay')->first();
    expect($row)->not->toBeNull()->and((int) $row->admin_id)->toBe($admin->id);
});
