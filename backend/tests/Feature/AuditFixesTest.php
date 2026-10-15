<?php

use App\Models\CorporateClient;
use App\Models\Partner;
use App\Models\Review;
use App\Models\SmsDispatch;
use App\Models\TbcPayment;
use App\Models\User;
use App\Services\Security\RetireDefaultPasswords;
use App\Services\Sms\MockSmsGateway;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Tymon\JWTAuth\Facades\JWTAuth;

beforeEach(function () {
    DB::statement('PRAGMA defer_foreign_keys = ON');
    Http::fake(['*' => Http::response(['Success' => true])]);
    Queue::fake();
    $this->withoutMiddleware(PreventRequestForgery::class);
    app(MockSmsGateway::class)->reset();
});

function afReset(): void
{
    foreach (['tymon.jwt', 'tymon.jwt.auth'] as $id) {
        app()->forgetInstance($id);
    }
    JWTAuth::clearResolvedInstances();
    app('auth')->forgetGuards();
}

function afStaff(string $phone, int $role, string $password): User
{
    $u = makeUser($phone, $role);
    $u->forceFill(['password' => Hash::make($password)])->save();

    return $u->fresh();
}

// C1 — default passwords
it('locks admin/manager/partner accounts that still use a default password', function () {
    $admin = afStaff('555600001', User::ROLE_ADMIN, 'admin123');
    $manager = afStaff('555600002', User::ROLE_MANAGER, 'manager123');
    $safeAdmin = afStaff('555600003', User::ROLE_ADMIN, 'a-strong-pass-1');
    $legacy = afStaff('555600004', User::ROLE_MANAGER, '34rbb45i43jnv8493uh');
    $pid = DB::table('partners')->insertGetId(['login' => 'partner', 'password' => Hash::make('partner123'), 'name' => 'P', 'description' => 'd', 'password_must_change' => false, 'created_at' => now(), 'updated_at' => now()]);
    $tv = (int) $admin->token_version;

    app(RetireDefaultPasswords::class)->handle();

    foreach ([$admin, $manager, $legacy] as $u) {
        $f = $u->fresh();
        expect((bool) $f->password_reset_required)->toBeTrue()
            ->and(Hash::check('admin123', $f->password) || Hash::check('manager123', $f->password))->toBeFalse();
    }
    expect((int) $admin->fresh()->token_version)->toBe($tv + 1);
    expect((bool) $safeAdmin->fresh()->password_reset_required)->toBeFalse()
        ->and(Hash::check('a-strong-pass-1', $safeAdmin->fresh()->password))->toBeTrue();
    $p = Partner::find($pid);
    expect(Hash::check('partner123', $p->password))->toBeFalse()->and((bool) $p->password_must_change)->toBeTrue();
    $this->postJson('/api/partner/login', ['login' => 'partner', 'password' => 'partner123'])->assertStatus(401);

    // The migration wires the same service.
    expect(file_get_contents(database_path('migrations/2026_10_15_000001_retire_default_staff_passwords.php')))->toContain('RetireDefaultPasswords');
});

it('blocks login for a locked account until a safe SMS reset', function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    $admin = afStaff('555600010', User::ROLE_ADMIN, 'new-pass-123');
    $admin->forceFill(['password_reset_required' => true])->save();

    $this->postJson('/api/login', ['phone' => '555600010', 'password' => 'new-pass-123'])->assertStatus(403)
        ->assertJsonPath('password_reset_required', true);
    $this->post('/login', ['email' => $admin->email ?? 'x@y.z', 'phone' => '555600010', 'password' => 'new-pass-123']);
    expect(auth()->check())->toBeFalse();

    $sms = $this->postJson('/api/change_password', ['phone' => '555600010'])->assertOk();
    $setup = $this->postJson('/api/change_password/verify', ['temp_code' => $sms->json('temp_code'), 'code' => app(MockSmsGateway::class)->latestCode()])->assertOk()->json('setup_token');
    $this->postJson('/api/change_password/verify_submit', ['setup_token' => $setup, 'password' => 'brand-new-pass'])->assertOk();
    expect((bool) $admin->fresh()->password_reset_required)->toBeFalse();
    afReset();
    $this->postJson('/api/login', ['phone' => '555600010', 'password' => 'brand-new-pass'])->assertOk();
});

it('keeps fixed passwords out of seeders', function () {
    foreach (glob(database_path('seeders/*.php')) as $f) {
        expect(file_get_contents($f))->not->toMatch('/(admin123|manager123|partner123|client123|34rbb45i43jnv8493uh)/');
    }
});

// H1 — package wash bound to branch
it('refuses a bare-id walk-in approve and accepts the scanned QR or a checkqr token', function () {
    $mgr = makeUser('555600020', User::ROLE_MANAGER);
    $client = makeUser('555600021');
    washFor($mgr);
    $car = row('user_cars', ['user_id' => $client->id, 'model_id' => 1, 'plate' => 'QR111QR']);
    $pkg = userPackage($client->id, $car, ['qr_code' => 'QRCODE0000000001']);
    $t = JWTAuth::fromUser($mgr);

    $this->withToken($t)->postJson("/api/package/{$pkg}/approve")->assertForbidden();
    $this->withToken($t)->postJson("/api/package/{$pkg}/approve", ['qr_code' => 'WRONG'])->assertForbidden();
    expect((int) DB::table('user_packages')->find($pkg)->used_washes)->toBe(0);

    $this->withToken($t)->postJson("/api/package/{$pkg}/approve", ['qr_code' => 'QRCODE0000000001'])->assertOk();
    $token = $this->withToken($t)->getJson('/api/checkqr/QRCODE0000000001')->assertOk()->json('approve_token');
    expect($token)->toBeString();
    $this->withToken($t)->postJson("/api/package/{$pkg}/approve", ['approve_token' => $token])->assertOk();
    // single use
    $this->withToken($t)->postJson("/api/package/{$pkg}/approve", ['approve_token' => $token])->assertForbidden();
    expect((int) DB::table('user_packages')->find($pkg)->used_washes)->toBe(2);

    // Another manager can't use this manager's token.
    $mgr2 = makeUser('555600022', User::ROLE_MANAGER);
    washFor($mgr2);
    $token = $this->withToken($t)->getJson('/api/checkqr/QRCODE0000000001')->json('approve_token');
    afReset();
    $this->withToken(JWTAuth::fromUser($mgr2))->postJson("/api/package/{$pkg}/approve", ['approve_token' => $token])->assertForbidden();
});

// H2 — test payment cap + sandbox 404
it('caps test payment amount and hides the sandbox in production unless enabled', function () {
    config(['security.test_payment_max_amount' => 100]);
    $admin = makeUser('555600030', User::ROLE_ADMIN);
    $t = JWTAuth::fromUser($admin);
    $this->withToken($t)->getJson('/api/testpay?amount=100000000')->assertStatus(422);
    $this->withToken($t)->getJson('/api/testpay?amount=100')->assertOk();
    expect(TbcPayment::query()->count())->toBe(1);

    app()['env'] = 'production';
    config(['security.test_payment_allow_production' => false]);
    $this->get('/payment/sandbox')->assertNotFound();
    $this->get('/payment/sandbox?order=x&access=y')->assertNotFound();
    app()['env'] = 'testing';
});

// M1 — refresh with rotation
it('refreshes the api token and revokes the old one', function () {
    $u = makeUser('555600040');
    $old = JWTAuth::fromUser($u);
    $new = $this->withToken($old)->postJson('/api/refresh')->assertOk()->json('access_token');
    expect($new)->toBeString()->not->toBe($old);
    afReset();
    $this->withToken($old)->getJson('/api/me')->assertUnauthorized();
    afReset();
    $this->withToken($new)->getJson('/api/me')->assertOk();
    expect((int) config('jwt.ttl'))->toBeLessThanOrEqual(1440);
});

// M2 — SMS pumping
it('limits OTP per phone regardless of IP and globally per day', function () {
    config(['security.otp_daily_global_limit' => 3]);
    $codes = [];
    foreach (range(1, 4) as $i) {
        $codes[] = $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.$i"])->postJson('/api/change_password', ['phone' => '555600050'])->status();
    }
    expect($codes)->toContain(429);
    $codes = [];
    foreach (range(1, 4) as $i) {
        $codes[] = $this->withServerVariables(['REMOTE_ADDR' => "10.1.0.$i"])->postJson('/api/register', ['phone' => "5556001$i".'0'])->status();
    }
    expect($codes)->toContain(429);
});

it('prunes stale unverified registration stubs', function () {
    $stub = User::forceCreate(['phone' => '555600060', 'password' => 'x', 'role' => 0, 'ref_code' => 'STB01']);
    DB::table('users')->where('id', $stub->id)->update(['created_at' => now()->subDays(3)]);
    $fresh = User::forceCreate(['phone' => '555600061', 'password' => 'x', 'role' => 0, 'ref_code' => 'STB02']);
    $verified = makeUser('555600062');
    DB::table('users')->where('id', $verified->id)->update(['created_at' => now()->subDays(3)]);
    $this->artisan('users:prune-unverified')->assertSuccessful();
    expect(User::find($stub->id))->toBeNull()->and(User::find($fresh->id))->not->toBeNull()->and(User::find($verified->id))->not->toBeNull();
});

// M3 — enumeration
it('does not reveal whether a phone is registered', function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    makeUser('555600070');
    $a = $this->postJson('/api/register', ['phone' => '555600070']);
    $b = $this->postJson('/api/register', ['phone' => '555600071']);
    expect($a->status())->toBe($b->status())->toBe(200);
    expect(array_keys($a->json()))->toEqualCanonicalizing(array_keys($b->json()));

    $a = $this->postJson('/api/change_password', ['phone' => '555600070']);
    $b = $this->postJson('/api/change_password', ['phone' => '555600079']);
    expect($a->status())->toBe($b->status())->toBe(200);

    $stub = User::forceCreate(['phone' => '555600072', 'password' => Hash::make('right-pass'), 'role' => 0, 'ref_code' => 'ENM01']);
    $this->postJson('/api/login', ['phone' => '555600072', 'password' => 'wrong-pass'])->assertStatus(401);
});

// M5 — audit coverage
it('audits every admin write and sms inbox views', function () {
    $admin = makeUser('555600080', User::ROLE_ADMIN);
    $c = CorporateClient::forceCreate(['name' => 'C', 'api_token_hash' => hash('sha256', 'x'), 'username' => 'caud', 'password' => 'x']);
    $this->actingAs($admin)->post('/dashboard/corporate/'.$c->id.'/token')->assertRedirect();
    $row = DB::table('admin_audit_logs')->where('action', 'admin.corporate.token')->first();
    expect($row)->not->toBeNull()->and((int) $row->admin_id)->toBe($admin->id)->and((int) $row->target_id)->toBe($c->id);
    config(['sms.driver' => 'mock', 'sms.mock_inbox_enabled' => true]);
    $this->actingAs($admin)->get('/dashboard/sms-inbox')->assertOk();
    expect(DB::table('admin_audit_logs')->where('action', 'admin.sms_inbox')->exists())->toBeTrue();
});

// M7 — corporate token hashed
it('stores only a hash of the corporate api token and shows it once', function () {
    $admin = makeUser('555600090', User::ROLE_ADMIN);
    $c = CorporateClient::forceCreate(['name' => 'C', 'api_token_hash' => hash('sha256', 'old'), 'username' => 'chash', 'password' => 'x', 'password_must_change' => false]);
    $res = $this->actingAs($admin)->post('/dashboard/corporate/'.$c->id.'/token');
    $plain = session('corporate_api_token');
    expect($plain)->toBeString()->and(strlen($plain))->toBeGreaterThanOrEqual(40);
    $c->refresh();
    expect($c->api_token_hash)->toBe(hash('sha256', $plain))->and(DB::table('corporate_clients')->where('id', $c->id)->value('api_token'))->toBeNull();
    auth()->logout();
    $this->getJson('/api/corporate/vehicles', ['X-Api-Key' => $plain])->assertOk();
    $this->getJson('/api/corporate/vehicles', ['X-Api-Key' => 'old'])->assertUnauthorized();
    $this->actingAs($admin)->get('/dashboard/corporate/'.$c->id)->assertOk()->assertSee($plain);
    $this->actingAs($admin)->get('/dashboard/corporate/'.$c->id)->assertOk()->assertDontSee($plain);
});

// M8 — mock inbox only for allowlisted phones
it('stores mock sms bodies only for allowlisted phones', function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    config(['sms.driver' => 'mock', 'sms.mock_inbox_enabled' => true, 'sms.mock_inbox_phones' => ['555600101']]);
    makeUser('555600100');
    makeUser('555600101');
    $this->postJson('/api/change_password', ['phone' => '555600100'])->assertOk();
    $this->postJson('/api/change_password', ['phone' => '555600101'])->assertOk();
    expect(SmsDispatch::query()->whereNotNull('body_encrypted')->count())->toBe(1);
});

// L2 — uniform voucher check
it('answers the same for unknown and foreign vouchers', function () {
    [, $tokenA] = partnerToken('af-partner-a');
    [$pidB] = partnerToken('af-partner-b');
    $owner = makeUser('555600110');
    $vid = row('vouchers', ['name' => 'V', 'description' => 'd', 'photo' => 'p', 'category_id' => row('voucher_categories', ['name' => 'c']), 'price' => 1, 'partner_id' => $pidB]);
    row('user_vouchers', ['user_id' => $owner->id, 'voucher_id' => $vid, 'code' => 'FOREIGN1']);
    $foreign = $this->withToken($tokenA)->postJson('/api/partner/voucher/check', ['code' => 'FOREIGN1']);
    $unknown = $this->withToken($tokenA)->postJson('/api/partner/voucher/check', ['code' => 'NOPE0000']);
    expect($foreign->status())->toBe($unknown->status())->and($foreign->json())->toBe($unknown->json());
});

// L3 — no svg uploads
it('rejects svg uploads in admin forms', function () {
    foreach (['Admin/TicketsController.php', 'Admin/VouchersController.php', 'Admin/BodyTypesController.php'] as $f) {
        expect(file_get_contents(app_path('Http/Controllers/'.$f)))->not->toMatch('/mimes:[^\'"]*svg/');
    }
});

// L4 — no raw json in voucher edit
it('does not echo the voucher model unescaped', function () {
    expect(file_get_contents(resource_path('views/admin/vouchers/edit.blade.php')))->not->toContain('{!! $voucher !!}');
});

// L5 — review toggle is POST
it('toggles review visibility only via POST', function () {
    $admin = makeUser('555600120', User::ROLE_ADMIN);
    $mgr = makeUser('555600121', User::ROLE_MANAGER);
    $wash = washFor($mgr);
    $rid = row('reviews', ['user_id' => $admin->id, 'car_wash_id' => $wash, 'stars' => 5, 'text' => 't', 'status' => 1]);
    $this->actingAs($admin)->get("/dashboard/washings/{$wash}/reviews/{$rid}/toggle")->assertStatus(405);
    $this->actingAs($admin)->post("/dashboard/washings/{$wash}/reviews/{$rid}/toggle")->assertRedirect();
    expect((bool) Review::find($rid)->status)->toBeFalse();
});

// L6 — trusted proxies from env
it('trusts only configured proxies', function () {
    config(['security.trusted_proxies' => '10.9.9.9']);
    $ip = null;
    Route::get('/__ip', function (Request $r) use (&$ip) {
        $ip = $r->ip();

        return 'ok';
    });
    $this->withServerVariables(['REMOTE_ADDR' => '10.9.9.9', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4'])->get('/__ip');
    expect($ip)->toBe('1.2.3.4');
    $this->withServerVariables(['REMOTE_ADDR' => '10.8.8.8', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4'])->get('/__ip');
    expect($ip)->toBe('10.8.8.8');
});

// L7 — OTP hash keyed
it('stores OTP codes as a keyed hash, not plain sha256', function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    makeUser('555600130');
    $this->postJson('/api/change_password', ['phone' => '555600130'])->assertOk();
    $code = app(MockSmsGateway::class)->latestCode();
    $row = DB::table('sms_temps')->latest('id')->first();
    expect($row->code)->not->toBe(hash('sha256', $code));
});

// L8 — account deletion anonymises
it('anonymises personal data on account deletion and allows deleting twice', function () {
    foreach ([1, 2] as $i) {
        $u = makeUser('555600140');
        $u->forceFill(['name' => 'Nino', 'surname' => 'B', 'email' => "n$i@example.com", 'date_of_birth' => '1990-01-01'])->save();
        afReset();
        $this->withToken(JWTAuth::fromUser($u))->postJson('/api/account/delete')->assertOk();
        $f = $u->fresh();
        expect($f->name)->toBeNull()->and($f->surname)->toBeNull()->and($f->email)->toBeNull()->and($f->date_of_birth)->toBeNull()
            ->and($f->phone)->not->toContain('555');
    }
});
