<?php

use App\Models\TbcPayment;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Tymon\JWTAuth\Facades\JWTAuth;

function tpUser(string $phone, int $role = User::ROLE_USER): User
{
    return makeUser($phone, $role);
}

function tpReset(): void
{
    foreach (['tymon.jwt', 'tymon.jwt.auth'] as $id) {
        app()->forgetInstance($id);
    }
    JWTAuth::clearResolvedInstances();
    app('auth')->forgetGuards();
}

function tpStart($test, User $user)
{
    tpReset();

    return $test->withToken(JWTAuth::fromUser($user))->getJson('/api/testpay?amount=5');
}

function tpPay($test, string $url)
{
    $q = [];
    parse_str((string) parse_url($url, PHP_URL_QUERY), $q);

    return $test->post('/payment/sandbox', ['order' => $q['order'], 'access' => $q['access'], 'decision' => 'pay', 'method' => 'apple']);
}

beforeEach(function () {
    // With env=production Laravel enforces CSRF even in tests; CSRF itself is covered in SecurityStage1Test.
    $this->withoutMiddleware(PreventRequestForgery::class);
    app()['env'] = 'production';
    config(['security.test_payment_phones' => ['+995 555 900 001']]);
});

afterEach(function () {
    app()['env'] = 'testing';
});

it('denies test payment to everyone in production when the flag is off', function () {
    config(['security.test_payment_allow_production' => false]);
    foreach ([tpUser('555900010', User::ROLE_ADMIN), tpUser('555900001'), tpUser('555900011')] as $u) {
        tpStart($this, $u)->assertNotFound();
    }
    expect(TbcPayment::query()->count())->toBe(0);
    $this->post('/payment/sandbox', ['order' => 'x', 'access' => 'y', 'decision' => 'pay', 'method' => 'apple'])->assertNotFound();
});

it('allows admin and a listed QA phone in production when the flag is on, crediting once', function () {
    config(['security.test_payment_allow_production' => true]);
    foreach ([tpUser('555900020', User::ROLE_ADMIN), tpUser('555900001')] as $u) {
        $start = tpStart($this, $u)->assertOk()->assertJsonPath('test_mode', true);
        tpPay($this, $start->json('url'))->assertRedirect();
        tpPay($this, $start->json('url'))->assertRedirect();
        expect((int) $u->fresh()->points)->toBe(5);
        expect(TbcPayment::query()->where('user_id', $u->id)->value('status'))->toBe('succeeded');
    }
});

it('denies a normal user and a foreign order in production when the flag is on', function () {
    config(['security.test_payment_allow_production' => true]);
    $normal = tpUser('555900030');
    tpStart($this, $normal)->assertForbidden();
    expect(TbcPayment::query()->where('user_id', $normal->id)->exists())->toBeFalse();

    // A real (non-test) order of the normal user can't be paid via the sandbox, even with a guessed access.
    $real = TbcPayment::query()->create([
        'merchant_payment_id' => 'PREALPROD0000001', 'user_id' => $normal->id, 'type' => 'points',
        'amount' => 7, 'currency' => 'GEL', 'status' => 'pending', 'test_mode' => false, 'payload' => ['points' => 7],
    ]);
    $this->post('/payment/sandbox', ['order' => $real->merchant_payment_id, 'access' => 'guess', 'decision' => 'pay', 'method' => 'apple']);
    expect($real->fresh()->status)->toBe('pending');

    // The QA tester's order paid with a wrong access token (someone else) is refused.
    $qa = tpUser('555900001');
    $start = tpStart($this, $qa)->assertOk();
    $q = [];
    parse_str((string) parse_url($start->json('url'), PHP_URL_QUERY), $q);
    $this->post('/payment/sandbox', ['order' => $q['order'], 'access' => str_repeat('0', 64), 'decision' => 'pay', 'method' => 'apple']);
    expect((int) $qa->fresh()->points)->toBe(0);

    // A test order whose owner is no longer an allowed tester can't be settled.
    config(['security.test_payment_phones' => []]);
    tpPay($this, $start->json('url'));
    expect((int) $qa->fresh()->points)->toBe(0)->and((int) $normal->fresh()->points)->toBe(0);
});
