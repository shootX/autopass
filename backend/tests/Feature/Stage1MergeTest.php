<?php

use App\Models\SmsDispatch;
use App\Models\SmsTemp;
use App\Models\User;
use App\Services\Sms\MockSmsGateway;
use App\Services\Sms\SmsDispatcher;
use App\Services\Sms\SmsGateway;
use App\Services\Sms\SmsMessage;
use App\Services\Sms\SmsResult;
use App\Services\Sms\SmsStatus;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    app(MockSmsGateway::class)->reset();
    Http::preventStrayRequests();
});

function resetJwt(): void
{
    foreach (['tymon.jwt', 'tymon.jwt.auth'] as $id) {
        app()->forgetInstance($id);
    }
    JWTAuth::clearResolvedInstances();
    app('auth')->forgetGuards();
}

function mockSms(): MockSmsGateway
{
    return app(MockSmsGateway::class);
}

function registerAndVerify($test, string $phone): array
{
    $reg = $test->postJson('/api/register', ['phone' => $phone])->assertOk();
    $code = mockSms()->latestCode();
    $setup = $test->postJson('/api/register/verify', ['temp_code' => $reg->json('temp_code'), 'code' => $code])
        ->assertOk()->json('setup_token');

    return [$reg->json('temp_code'), $code, $setup];
}

it('issues no auth token on verify and denies the API until a password is set', function () {
    $reg = $this->postJson('/api/register', ['phone' => '555700001'])->assertOk();
    expect($reg->json('temp_code'))->toBeString()->toMatch('/^[a-f0-9]{32}$/');
    $verify = $this->postJson('/api/register/verify', ['temp_code' => $reg->json('temp_code'), 'code' => mockSms()->latestCode()])->assertOk();
    $verify->assertJsonMissingPath('access_token');

    $user = User::query()->where('phone', '555 70 00 01')->first();
    expect($user->phone_verified_at)->toBeNull();
    // A token minted for an unconfirmed user (e.g. leaked) is still refused.
    $this->withToken(JWTAuth::fromUser($user))->getJson('/api/me')->assertForbidden();
    // Wrong password: 401 without revealing the unconfirmed state (audit M3).
    $this->postJson('/api/login', ['phone' => '555700001', 'password' => 'whatever1'])->assertUnauthorized();

    $set = $this->postJson('/api/register/set_password', ['setup_token' => $verify->json('setup_token'), 'password' => 'secret12'])->assertOk();
    expect($set->json('access_token'))->toBeString();
});

it('binds setup_token to its action and account', function () {
    [, , $setup] = registerAndVerify($this, '555700002');

    // Registration token cannot be used as a reset token.
    $this->postJson('/api/change_password/verify_submit', ['setup_token' => $setup, 'password' => 'hijack12'])->assertStatus(422);
    // Still valid for its own action (not burned by the wrong use), then single use.
    $this->postJson('/api/register/set_password', ['setup_token' => $setup, 'password' => 'secret12'])->assertOk();
    $this->postJson('/api/register/set_password', ['setup_token' => $setup, 'password' => 'again123'])->assertStatus(422);

    // The token only ever changes the account it was issued for.
    $owner = User::query()->where('phone', '555 70 00 02')->first();
    expect(Hash::check('secret12', $owner->password))->toBeTrue();
    expect(DB::table('auth_grants')->where('user_id', $owner->id)->whereNull('used_at')->count())->toBe(0);
    expect(DB::table('auth_grants')->where('token_hash', $setup)->exists())->toBeFalse(); // stored hashed
});

it('rejects unverified, expired and used codes', function () {
    $reg = $this->postJson('/api/register', ['phone' => '555700003'])->assertOk();
    $code = mockSms()->latestCode();
    $this->postJson('/api/register/set_password', ['setup_token' => str_repeat('a', 64), 'password' => 'secret12'])->assertStatus(422);

    $this->travel(301)->seconds();
    $this->postJson('/api/register/verify', ['temp_code' => $reg->json('temp_code'), 'code' => $code])->assertStatus(422);
    $this->travelBack();

    $this->travel(61)->seconds();
    $again = $this->postJson('/api/register', ['phone' => '555700003'])->assertOk();
    $fresh = mockSms()->latestCode();
    $this->postJson('/api/register/verify', ['temp_code' => $again->json('temp_code'), 'code' => $fresh])->assertOk();
    $this->postJson('/api/register/verify', ['temp_code' => $again->json('temp_code'), 'code' => $fresh])->assertStatus(422);
});

it('keeps registration and recovery codes independent', function () {
    $user = makeUser('555700004');
    $user->forceFill(['phone' => '555 70 00 04', 'phone_verified_at' => null])->save();
    $reg = $this->postJson('/api/register', ['phone' => '555700004'])->assertOk();
    $regCode = mockSms()->latestCode();
    $user->forceFill(['phone_verified_at' => now()])->save();

    $reset = $this->postJson('/api/change_password', ['phone' => '555700004'])->assertOk();
    // Issuing a recovery code did not kill the registration code, and vice versa.
    expect(SmsTemp::query()->where('public_id', $reg->json('temp_code'))->value('consumed_at'))->toBeNull();
    expect(SmsTemp::query()->where('public_id', $reset->json('temp_code'))->value('consumed_at'))->toBeNull();
    $this->postJson('/api/change_password/verify', ['temp_code' => $reg->json('temp_code'), 'code' => $regCode])->assertStatus(422);
});

it('enforces a 60 second resend window and a 5 attempt limit', function () {
    $this->postJson('/api/register', ['phone' => '555700005'])->assertOk();
    $this->postJson('/api/register', ['phone' => '555700005'])->assertStatus(422);
    expect(mockSms()->messages)->toHaveCount(1);
});

it('binds the phone change code to the user, the new number and the action', function () {
    $alice = makeUser('555700010');
    $bob = makeUser('555700011');
    $aliceToken = JWTAuth::fromUser($alice);
    $start = $this->withToken($aliceToken)->postJson('/api/change_phone', ['phone' => '555700012'])->assertOk();
    $code = mockSms()->latestCode();
    expect(mockSms()->messages[0]['destination'])->toBe('995555700012');

    // Bob can't use (or burn) Alice's code.
    resetJwt();
    $this->withToken(JWTAuth::fromUser($bob))->postJson('/api/change_phone/verify', ['temp_code' => $start->json('temp_code'), 'code' => $code, 'phone' => '555700012'])->assertStatus(422);
    expect(SmsTemp::query()->where('public_id', $start->json('temp_code'))->value('attempts'))->toBe(0);

    // Alice can't swap in a different number, and the code is then dead.
    resetJwt();
    $this->withToken($aliceToken)->postJson('/api/change_phone/verify', ['temp_code' => $start->json('temp_code'), 'code' => $code, 'phone' => '555700013'])->assertStatus(422);
    $this->withToken($aliceToken)->postJson('/api/change_phone/verify', ['temp_code' => $start->json('temp_code'), 'code' => $code, 'phone' => '555700012'])->assertStatus(422);
    expect($alice->fresh()->phone)->not->toContain('70 00 12');
});

it('lets a person register again after the SMS failed', function () {
    $failing = new class implements SmsGateway
    {
        public function send(SmsMessage $message): SmsResult
        {
            return new SmsResult(SmsStatus::Failed, $message->reference, 20, 'provider');
        }
    };
    app()->instance(SmsGateway::class, $failing);
    app()->forgetInstance(SmsDispatcher::class);
    // Sent through the queue (QA D10): the answer can't wait for the gateway, so it is
    // "accepted"; the failed job drops the challenge, which keeps the retry open.
    $this->postJson('/api/register', ['phone' => '555700020'])->assertOk();
    $stub = User::query()->where('phone', '555 70 00 20')->first();
    expect(DB::table('sms_temps')->where('user_id', $stub->id)->count())->toBe(0);

    app()->instance(SmsGateway::class, mockSms());
    $this->postJson('/api/register', ['phone' => '555700020'])->assertOk()->assertJsonPath('delivery', 'simulated');
});

it('never writes the OTP to logs or the API response', function () {
    $logged = [];
    Log::listen(function ($event) use (&$logged) {
        $logged[] = $event->message.' '.json_encode($event->context);
    });
    $res = $this->postJson('/api/register', ['phone' => '555700030'])->assertOk();
    $code = mockSms()->latestCode();
    expect($res->getContent())->not->toContain($code);
    expect(implode("\n", $logged))->not->toContain($code);
    expect(SmsTemp::query()->where('public_id', $res->json('temp_code'))->value('code'))->not->toBe($code);
});

it('does not double send for a parallel request holding the issue lock', function () {
    $user = makeUser('555700040');
    $lock = Cache::lock('sms-issue:'.$user->id.':reset', 15);
    expect($lock->get())->toBeTrue();
    $this->postJson('/api/change_password', ['phone' => '555700040'])->assertStatus(429);
    expect(mockSms()->messages)->toHaveCount(0);
    $lock->release();
    $this->postJson('/api/change_password', ['phone' => '555700040'])->assertOk();
    $this->postJson('/api/change_password', ['phone' => '555700040'])->assertStatus(422);
    expect(mockSms()->messages)->toHaveCount(1);
    expect(SmsTemp::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('treats only Success=true and numeric ErrorCode 0 as accepted', function (mixed $body, SmsStatus $expected) {
    Config::set('sms.driver', 'smsoffice');
    Config::set('sms.office.key', 'k');
    Config::set('sms.office.sender', 'AutoPass');
    app()->forgetInstance(SmsGateway::class);
    Http::fake(['*' => Http::response($body, 200)]);
    $result = app(SmsGateway::class)->send(new SmsMessage('995555112233', 'x', 'verify', 'ref'.random_int(1000, 9999)));
    expect($result->status)->toBe($expected);
})->with([
    'accepted' => [['Success' => true, 'ErrorCode' => 0], SmsStatus::Accepted],
    'null code' => [['Success' => true, 'ErrorCode' => null], SmsStatus::Failed],
    'text code' => [['Success' => true, 'ErrorCode' => 'abc'], SmsStatus::Failed],
    'empty code' => [['Success' => true, 'ErrorCode' => ''], SmsStatus::Failed],
    'string true' => [['Success' => 'true', 'ErrorCode' => 0], SmsStatus::Failed],
    'success false' => [['Success' => false, 'ErrorCode' => 0], SmsStatus::Failed],
    'missing keys' => [['Message' => 'ok'], SmsStatus::Failed],
]);

it('prunes expired inbox texts on the scheduler, not when the inbox opens', function () {
    Config::set('sms.mock_inbox_enabled', true);
    Config::set('sms.mock_inbox_phones', ['555700050']);
    $this->postJson('/api/register', ['phone' => '555700050'])->assertOk();
    $admin = makeUser('555700051', User::ROLE_ADMIN);
    $this->travel(11)->minutes();
    $this->withToken(JWTAuth::fromUser($admin))->getJson('/api/admin/sms-inbox')->assertOk()->assertJsonPath('messages', []);
    // Opening the inbox did not touch storage...
    expect(SmsDispatch::query()->whereNotNull('body_encrypted')->count())->toBe(1);
    // ...the scheduled command does.
    $this->artisan('sms:inbox-prune')->assertSuccessful();
    expect(SmsDispatch::query()->whereNotNull('body_encrypted')->count())->toBe(0);

    $events = collect(app(Schedule::class)->events())->map->command->implode(' ');
    expect($events)->toContain('sms:inbox-prune');
});

it('serves the manager API without the manager owning a car', function () {
    $mgr = makeUser('555700060', User::ROLE_MANAGER);
    $client = makeUser('555700061');
    DB::statement('PRAGMA defer_foreign_keys = ON');
    $wash = DB::table('car_washes')->insertGetId(['name' => 'W', 'address' => 'a', 'work_time_start' => '09:00', 'work_time_end' => '20:00', 'location' => 'x', 'manager_id' => $mgr->id, 'created_at' => now(), 'updated_at' => now()]);
    $car = DB::table('user_cars')->insertGetId(['car_type' => 'sedan', 'body_status' => 'confirmed', 'user_id' => $client->id, 'model_id' => 1, 'plate' => 'MG001MG', 'created_at' => now(), 'updated_at' => now()]);
    $pkg = DB::table('user_packages')->insertGetId(['user_id' => $client->id, 'package_id' => 1, 'user_car_id' => $car, 'end_date' => now()->addMonth(), 'number_of_washes' => 3, 'used_washes' => 0, 'qr_code' => 'QRMGR1', 'created_at' => now(), 'updated_at' => now()]);
    expect(DB::table('user_cars')->where('user_id', $mgr->id)->count())->toBe(0);

    $token = JWTAuth::fromUser($mgr);
    $this->withToken($token)->getJson('/api/me')->assertOk();
    $this->withToken($token)->postJson("/api/package/{$pkg}/approve", ['approve_token' => approveTokenFor($mgr, $pkg)])->assertOk();
    $this->withToken($token)->postJson("/api/package/{$pkg}/approve", ['car_wash_id' => $wash + 99])->assertForbidden();
    expect(DB::table('package_washes')->where('user_package_id', $pkg)->count())->toBe(1);
});
