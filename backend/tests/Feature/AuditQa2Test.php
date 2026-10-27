<?php

use App\Jobs\SendSms;
use App\Models\PackageWash;
use App\Models\TbcPayment;
use App\Models\User;
use App\Services\Security\RefreshTokens;
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
    $this->withoutMiddleware(PreventRequestForgery::class);
    $this->withoutMiddleware(ThrottleRequests::class);
    app(MockSmsGateway::class)->reset();
});

function qa2Reset(): void
{
    foreach (['tymon.jwt', 'tymon.jwt.auth'] as $id) {
        app()->forgetInstance($id);
    }
    JWTAuth::clearResolvedInstances();
    app('auth')->forgetGuards();
}

function qa2Package(string $suffix): array
{
    $mgr = makeUser('5557700'.$suffix.'1', User::ROLE_MANAGER);
    $client = makeUser('5557700'.$suffix.'2');
    $wash = washFor($mgr);
    $car = row('user_cars', ['car_type' => 'sedan', 'body_status' => 'confirmed', 'user_id' => $client->id, 'model_id' => 1, 'plate' => 'QB'.$suffix.'00QB']);
    $pkg = userPackage($client->id, $car, ['qr_code' => 'QBQR'.$suffix.'000000000']);

    return [$mgr, $client, $wash, $car, $pkg];
}

function qa2Login(object $test, string $phone, string $password = 'secret-pass-1')
{
    return $test->postJson('/api/login', ['phone' => $phone, 'password' => $password]);
}

function qa2Client(string $phone): User
{
    $u = makeUser($phone);
    $u->forceFill(['password' => Hash::make('secret-pass-1'), 'phone_verified_at' => now()])->save();

    return $u;
}

// D13
it('never approves with the static qr code alone', function () {
    [$mgr, , , , $pkg] = qa2Package('1');
    $t = JWTAuth::fromUser($mgr);
    foreach (range(1, 3) as $i) {
        $this->withToken($t)->postJson("/api/package/{$pkg}/approve", ['qr_code' => 'QBQR1000000000'])->assertForbidden();
    }
    expect((int) DB::table('user_packages')->where('id', $pkg)->value('used_washes'))->toBe(0);

    // The UI body (qr + token) works once; replaying the same body is refused.
    $body = ['qr_code' => 'QBQR1000000000', 'approve_token' => approveTokenFor($mgr, $pkg)];
    $this->withToken($t)->postJson("/api/package/{$pkg}/approve", $body)->assertOk();
    $this->withToken($t)->postJson("/api/package/{$pkg}/approve", $body)->assertForbidden();
    expect((int) DB::table('user_packages')->where('id', $pkg)->value('used_washes'))->toBe(1);
});

it('serialises approvals per package and keeps the token when turned away', function () {
    [$mgr, , , , $pkg] = qa2Package('2');
    $t = JWTAuth::fromUser($mgr);
    $token = approveTokenFor($mgr, $pkg);
    $held = Cache::lock('package-approve-pkg:'.$pkg, 15);
    expect($held->get())->toBeTrue();
    $this->withToken($t)->postJson("/api/package/{$pkg}/approve", ['approve_token' => $token])->assertStatus(409);
    $held->release();
    $this->withToken($t)->postJson("/api/package/{$pkg}/approve", ['approve_token' => $token])->assertOk();
    expect((int) DB::table('user_packages')->where('id', $pkg)->value('used_washes'))->toBe(1);
});

it('needs a token for booked cars too and links only the first wash to the booking', function () {
    [$mgr, $client, $wash, $car, $pkg] = qa2Package('3');
    $appt = row('appointments', ['user_id' => $client->id, 'car_wash_id' => $wash, 'car_id' => $car, 'date' => now()->toDateString(), 'time' => '10:00', 'qr_code' => 'A'.uniqid()]);
    $t = JWTAuth::fromUser($mgr);
    $this->withToken($t)->postJson("/api/package/{$pkg}/approve")->assertForbidden();
    $this->withToken($t)->postJson("/api/package/{$pkg}/approve", ['approve_token' => approveTokenFor($mgr, $pkg)])->assertOk();
    $this->withToken($t)->postJson("/api/package/{$pkg}/approve", ['approve_token' => approveTokenFor($mgr, $pkg)])->assertOk();
    expect(PackageWash::query()->where('user_package_id', $pkg)->orderBy('id')->pluck('appointment_id')->all())->toBe([$appt, null]);
});

// D12
it('checks the daily test payment cap under a per-user lock', function () {
    $admin = makeUser('555770401', User::ROLE_ADMIN);
    $t = JWTAuth::fromUser($admin);
    $held = Cache::lock('testpay:'.$admin->id, 10);
    expect($held->get())->toBeTrue();
    $this->withToken($t)->getJson('/api/testpay?amount=100')->assertStatus(409);
    expect(TbcPayment::query()->where('user_id', $admin->id)->count())->toBe(0);
    $held->release();
    $this->withToken($t)->getJson('/api/testpay?amount=100')->assertOk();
});

// D11
it('gives deleted accounts unique non-phone tombstones', function () {
    // The same number registered and deleted twice, plus another one: no unique-index 500.
    $phones = [];
    foreach (['555770501', '555770502', '555770501'] as $phone) {
        $u = qa2Client($phone);
        $this->withToken(JWTAuth::fromUser($u))->postJson('/api/account/delete')->assertOk();
        qa2Reset();
        $phones[] = (string) DB::table('users')->where('id', $u->id)->value('phone');
        expect(end($phones))->toStartWith('deleted-'.$u->id.'-');
    }
    expect(count(array_unique($phones)))->toBe(3);
    foreach ($phones as $p) {
        expect($p)->toStartWith('deleted-')->and(preg_match('/^\d+$/', $p))->toBe(0);
    }
});

// D10
it('dispatches the sms job for known and unknown numbers alike, never the gateway inline', function () {
    Queue::fake();
    qa2Client('555770601');
    $paths = [
        ['/api/register', '555770601'], ['/api/register', '555770602'],
        ['/api/change_password', '555770601'], ['/api/change_password', '555770699'],
    ];
    foreach ($paths as [$url, $phone]) {
        $before = count(Queue::pushed(SendSms::class));
        $r = $this->postJson($url, ['phone' => $phone])->assertOk();
        expect(count(Queue::pushed(SendSms::class)))->toBe($before + 1)
            ->and($r->json('delivery'))->toBe('simulated');
    }
    $me = qa2Client('555770603');
    $t = JWTAuth::fromUser($me);
    foreach (['555770601', '555770604'] as $phone) {
        $before = count(Queue::pushed(SendSms::class));
        $this->withToken($t)->postJson('/api/change_phone', ['phone' => $phone])->assertOk();
        expect(count(Queue::pushed(SendSms::class)))->toBe($before + 1);
    }
    expect(DB::table('sms_dispatches')->count())->toBe(0);
});

// Refresh cookie
it('sets a strict httpOnly refresh cookie scoped to /api/refresh on login', function () {
    qa2Client('555770701');
    $r = qa2Login($this, '555770701')->assertOk();
    $c = $r->getCookie(RefreshTokens::COOKIE, false);
    expect($c)->not->toBeNull()
        ->and($c->isHttpOnly())->toBeTrue()
        ->and($c->isSecure())->toBeTrue()
        ->and($c->getSameSite())->toBe('strict')
        ->and($c->getPath())->toBe('/api/refresh');
});

it('refreshes from the cookie alone, rotates it and treats reuse as theft', function () {
    qa2Client('555770702');
    $first = qa2Login($this, '555770702')->getCookie(RefreshTokens::COOKIE, false)->getValue();
    qa2Reset();
    $r = $this->withCredentials()->withUnencryptedCookie(RefreshTokens::COOKIE, $first)->postJson('/api/refresh')->assertOk();
    $second = $r->getCookie(RefreshTokens::COOKIE, false)->getValue();
    expect($second)->not->toBe($first);
    qa2Reset();
    $this->withToken($r->json('access_token'))->getJson('/api/me')->assertOk();

    $this->travel(31)->seconds(); // past the two-tab grace window (round 3, D14)
    qa2Reset();
    $this->withCredentials()->withUnencryptedCookie(RefreshTokens::COOKIE, $first)->postJson('/api/refresh')->assertStatus(401);
    qa2Reset();
    // Reuse revoked the whole family, including the newer token.
    $this->withCredentials()->withUnencryptedCookie(RefreshTokens::COOKIE, $second)->postJson('/api/refresh')->assertStatus(401);
});

it('ends cookie sessions at the absolute maximum, on logout and on password change', function () {
    config(['security.session_max_minutes' => 60]);
    $u = qa2Client('555770703');
    $c = qa2Login($this, '555770703')->getCookie(RefreshTokens::COOKIE, false)->getValue();
    $this->travel(61)->minutes();
    qa2Reset();
    $this->withCredentials()->withUnencryptedCookie(RefreshTokens::COOKIE, $c)->postJson('/api/refresh')->assertStatus(401);
    $this->travelBack();

    qa2Reset();
    $login = qa2Login($this, '555770703');
    $c = $login->getCookie(RefreshTokens::COOKIE, false)->getValue();
    qa2Reset();
    $this->withToken($login->json('access_token'))->postJson('/api/logout')->assertOk();
    qa2Reset();
    $this->withCredentials()->withUnencryptedCookie(RefreshTokens::COOKIE, $c)->postJson('/api/refresh')->assertStatus(401);

    qa2Reset();
    $c = qa2Login($this, '555770703')->getCookie(RefreshTokens::COOKIE, false)->getValue();
    $u->fresh()->forceFill(['token_version' => (int) $u->fresh()->token_version + 1])->save();
    qa2Reset();
    $this->withCredentials()->withUnencryptedCookie(RefreshTokens::COOKIE, $c)->postJson('/api/refresh')->assertStatus(401);
});

it('allows credentialed CORS only for configured origins', function () {
    config(['cors.allowed_origins' => ['https://app.example.test']]);
    $ok = $this->call('OPTIONS', '/api/refresh', [], [], [], [
        'HTTP_ORIGIN' => 'https://app.example.test', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
    ]);
    expect($ok->headers->get('Access-Control-Allow-Origin'))->toBe('https://app.example.test')
        ->and($ok->headers->get('Access-Control-Allow-Credentials'))->toBe('true');
    $bad = $this->call('OPTIONS', '/api/refresh', [], [], [], [
        'HTTP_ORIGIN' => 'https://evil.example.test', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
    ]);
    expect($bad->headers->get('Access-Control-Allow-Origin'))->not->toBe('https://evil.example.test')
        ->and($bad->headers->get('Access-Control-Allow-Origin'))->not->toBe('*');
    expect(config('cors.supports_credentials'))->toBeTrue();
});

// QA round 3
it('keeps the session when two tabs refresh with the same cookie (grace window)', function () {
    qa2Client('555770801');
    $first = qa2Login($this, '555770801')->getCookie(RefreshTokens::COOKIE, false)->getValue();
    qa2Reset();
    $a = $this->withCredentials()->withUnencryptedCookie(RefreshTokens::COOKIE, $first)->postJson('/api/refresh')->assertOk();
    $this->travel(10)->seconds();
    qa2Reset();
    // The second tab's request arrives after the rotation: still a race, not theft.
    $b = $this->withCredentials()->withUnencryptedCookie(RefreshTokens::COOKIE, $first)->postJson('/api/refresh')->assertOk();
    foreach ([$a, $b] as $r) {
        qa2Reset();
        $this->withCredentials()->withUnencryptedCookie(RefreshTokens::COOKIE, $r->getCookie(RefreshTokens::COOKIE, false)->getValue())
            ->postJson('/api/refresh')->assertOk();
    }
});

it('treats reuse after the grace window as theft', function () {
    qa2Client('555770802');
    $first = qa2Login($this, '555770802')->getCookie(RefreshTokens::COOKIE, false)->getValue();
    qa2Reset();
    $next = $this->withCredentials()->withUnencryptedCookie(RefreshTokens::COOKIE, $first)->postJson('/api/refresh')
        ->assertOk()->getCookie(RefreshTokens::COOKIE, false)->getValue();
    $this->travel(31)->seconds();
    qa2Reset();
    $this->withCredentials()->withUnencryptedCookie(RefreshTokens::COOKIE, $first)->postJson('/api/refresh')->assertStatus(401);
    qa2Reset();
    $this->withCredentials()->withUnencryptedCookie(RefreshTokens::COOKIE, $next)->postJson('/api/refresh')->assertStatus(401);
});

it('logs out by the refresh cookie even when the access token expired', function () {
    qa2Client('555770803');
    $c = qa2Login($this, '555770803')->getCookie(RefreshTokens::COOKIE, false)->getValue();
    $this->travel(2)->days(); // access token (24 h) expired, session (30 d) not
    qa2Reset();
    $out = $this->withCredentials()->withUnencryptedCookie(RefreshTokens::COOKIE, $c)->postJson('/api/refresh/logout')->assertOk();
    $cleared = $out->getCookie(RefreshTokens::COOKIE, false);
    expect($cleared->getValue())->toBe('')->and($cleared->getPath())->toBe('/api/refresh')
        ->and($cleared->getExpiresTime())->toBeLessThan(time());
    qa2Reset();
    $this->withCredentials()->withUnencryptedCookie(RefreshTokens::COOKIE, $c)->postJson('/api/refresh')->assertStatus(401);
    qa2Reset();
    $this->postJson('/api/refresh/logout')->assertOk(); // no cookie: idempotent
});

it('answers a parallel registration of the same new number without a 500', function () {
    Queue::fake();
    // Simulates the losing request: the number gets taken between its lookup and its insert.
    User::creating(function (User $u) {
        if ($u->phone === '555 77 09 01' && ! User::query()->where('phone', '555 77 09 01')->exists()) {
            DB::table('users')->insert(['phone' => '555 77 09 01', 'password' => 'x', 'role' => 0, 'ref_code' => 'ZZZZZ', 'created_at' => now(), 'updated_at' => now()]);
        }
    });
    $r = $this->postJson('/api/register', ['phone' => '555770901']);
    expect($r->status())->toBeLessThan(500);
    expect(User::query()->where('phone', '555 77 09 01')->count())->toBe(1);
});

it('retries SMS jobs with backoff', function () {
    $job = new SendSms;
    expect($job->tries)->toBeGreaterThanOrEqual(3)->and($job->backoff())->not->toBeEmpty();
});
