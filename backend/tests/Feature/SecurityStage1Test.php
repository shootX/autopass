<?php

namespace Tests\Feature;

use App\Mail\TemporaryPasswordMail;
use App\Models\Appointment;
use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\CarWash;
use App\Models\CorporateClient;
use App\Models\Package;
use App\Models\Partner;
use App\Models\TbcPayment;
use App\Models\User;
use App\Models\UserCar;
use App\Models\UserPackage;
use App\Models\UserVoucher;
use App\Models\Voucher;
use App\Models\VoucherCategory;
use App\Services\Payments\FlittCheckout;
use App\Services\Security\FakeSmsSender;
use App\Services\Security\RetireGuessablePasswords;
use App\Support\Phone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SecurityStage1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sms()->reset();
        if ($this->name() !== 'test_failed_temporary_password_email_does_not_open_the_account') {
            Mail::fake();
        }
    }

    public function test_registration_requires_sms_before_login_and_hides_the_code(): void
    {
        $phone = '555112233';

        $registered = $this->postJson('/api/register', ['phone' => '+995 555 11 22 33']);
        $registered->assertOk()->assertJsonPath('success', true);
        $code = $this->sms()->latestCode();
        $this->assertNotNull($code);
        $this->assertStringNotContainsString($code, $registered->getContent());
        $registered->assertJsonMissingPath('sms_code');

        $user = User::query()->where('phone', Phone::normalize($phone))->first();
        $this->assertNotNull($user);
        $this->assertNull($user->phone_verified_at);
        $this->assertFalse(Hash::check('34rbb45i43jnv8493uh', $user->password));

        $this->postJson('/api/login', ['phone' => $phone, 'password' => 'secret12'])
            ->assertForbidden()
            ->assertJsonPath('error', 'Account is not confirmed');

        $this->postJson('/api/register/set_password', [
            'temp_code' => $registered->json('temp_code'),
            'password' => 'secret12',
        ])->assertStatus(422);
        $this->assertNull($user->fresh()->phone_verified_at);

        $verified = $this->postJson('/api/register/verify', [
            'temp_code' => $registered->json('temp_code'),
            'code' => $code,
        ])->assertOk();
        $verified->assertJsonMissingPath('access_token');
        $this->assertStringNotContainsString($code, $verified->getContent());

        $finished = $this->postJson('/api/register/set_password', [
            'setup_token' => $verified->json('setup_token'),
            'password' => 'secret12',
        ])->assertOk();
        $this->assertNotEmpty($finished->json('access_token'));
        $this->assertNotNull($user->fresh()->phone_verified_at);
        $this->assertTrue(Hash::check('secret12', $user->fresh()->password));

        $this->postJson('/api/register/set_password', [
            'setup_token' => $verified->json('setup_token'),
            'password' => 'another1',
        ])->assertStatus(422);
        $this->assertTrue(Hash::check('secret12', $user->fresh()->password));

        $this->postJson('/api/login', ['phone' => '+995555112233', 'password' => 'secret12'])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_expired_used_and_replaced_codes_stop_working(): void
    {
        $this->postJson('/api/register', ['phone' => '555223344'])->assertOk();
        $first = $this->sms()->latestCode();
        $temp = User::query()->latest('id')->value('id');
        $public = \App\Models\SmsTemp::query()->where('user_id', $temp)->value('public_id');

        $this->travel(6)->minutes();
        $this->postJson('/api/register/verify', ['temp_code' => $public, 'code' => $first])
            ->assertStatus(422);

        $this->travelBack();
        $this->sms()->reset();
        $this->postJson('/api/register', ['phone' => '555334455'])->assertOk();
        $code = $this->sms()->latestCode();
        $userId = User::query()->where('phone', '555 33 44 55')->value('id');
        $challenge = \App\Models\SmsTemp::query()->where('user_id', $userId)->latest('id')->value('public_id');
        $grant = $this->postJson('/api/register/verify', ['temp_code' => $challenge, 'code' => $code])->json('setup_token');

        $this->travel(61)->seconds();
        $this->postJson('/api/register', ['phone' => '555334455'])->assertOk();
        $this->postJson('/api/register/verify', ['temp_code' => $challenge, 'code' => $code])->assertStatus(422);
        $this->postJson('/api/register/set_password', [
            'setup_token' => $grant,
            'password' => 'secret12',
        ])->assertStatus(422);

        $this->travelBack();
        $this->sms()->reset();
        $this->postJson('/api/register', ['phone' => '555445566'])->assertOk();
        $wrongUser = User::query()->where('phone', '555 44 55 66')->value('id');
        $wrongChallenge = \App\Models\SmsTemp::query()->where('user_id', $wrongUser)->value('public_id');
        $right = $this->sms()->latestCode();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/register/verify', ['temp_code' => $wrongChallenge, 'code' => '000000'])->assertStatus(422);
        }
        $this->postJson('/api/register/verify', ['temp_code' => $wrongChallenge, 'code' => $right])->assertStatus(422);
    }

    public function test_password_is_hashed_once(): void
    {
        $user = $this->customer('555667788', 'secret12');
        $this->assertTrue(Hash::check('secret12', $user->password));
        $this->assertSame(1, preg_match('/^\$2y\$/', $user->password));
    }

    public function test_reset_and_email_follow_the_same_grant_rules(): void
    {
        $user = $this->customer('555778899', 'secret12');
        $this->postJson('/api/change_password', ['phone' => '+995 555 77 88 99'])->assertOk();
        $code = $this->sms()->latestCode();
        $response = $this->postJson('/api/change_password', ['phone' => '555778899']);
        $this->assertStringNotContainsString((string) $code, $response->getContent());

        $challenge = \App\Models\SmsTemp::query()->where('user_id', $user->id)->where('type', 'reset')->value('public_id');
        $verified = $this->postJson('/api/change_password/verify', [
            'temp_code' => $challenge,
            'code' => $code,
        ])->assertOk();
        $verified->assertJsonMissingPath('verify_code');

        $this->postJson('/api/change_password/verify_submit', [
            'setup_token' => $verified->json('setup_token'),
            'password' => 'newpass1',
        ])->assertOk();
        $this->assertTrue(Hash::check('newpass1', $user->fresh()->password));
        $this->postJson('/api/change_password/verify_submit', [
            'setup_token' => $verified->json('setup_token'),
            'password' => 'otherpass',
        ])->assertStatus(422);

        $token = auth('api')->login($user->fresh());
        $this->withToken($token)->postJson('/api/email/set', ['email' => 'person@example.com'])->assertOk();
        Mail::assertSent(\App\Mail\VerificationMail::class, function ($mail) use (&$emailCode) {
            $emailCode = $mail->code;
            return true;
        });
        $set = $this->withToken($token)->postJson('/api/email/set', ['email' => 'person@example.com']);
        $this->assertStringNotContainsString($emailCode, $set->getContent());
        $this->withToken($token)->postJson('/api/email/verify', ['code' => $emailCode])->assertOk();
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->withToken($token)->postJson('/api/email/verify', ['code' => $emailCode])->assertStatus(422);

        $this->withToken($token)->postJson('/api/change_phone', ['phone' => '+995 555 18 18 18'])->assertOk();
        $phoneCode = $this->sms()->latestCode();
        $this->assertSame('995555181818', $this->sms()->messages[array_key_last($this->sms()->messages)]['destination']);
        $phoneChallenge = \App\Models\SmsTemp::query()->where('user_id', $user->id)->where('type', 'change')->latest('id')->value('public_id');
        $this->withToken($token)->postJson('/api/change_phone/verify', [
            'temp_code' => $phoneChallenge,
            'code' => '000000',
            'phone' => '555181818',
        ])->assertStatus(422);
        $this->assertSame('555 77 88 99', $user->fresh()->phone);
        $changed = $this->withToken($token)->postJson('/api/change_phone/verify', [
            'temp_code' => $phoneChallenge,
            'code' => $phoneCode,
            'phone' => '555181818',
        ]);
        $changed->assertOk();
        $this->assertStringNotContainsString((string) $phoneCode, $changed->getContent());
        $this->assertSame('555 18 18 18', $user->fresh()->phone);
    }

    public function test_corporate_and_partner_temporary_passwords(): void
    {
        $admin = $this->customer('555101010', 'adminpass', User::ROLE_ADMIN);
        $this->actingAs($admin);

        $first = $this->corporatePayload('100000001', 'one@example.com');
        $this->post('/dashboard/corporate/store', $first)->assertRedirect();
        $second = $this->corporatePayload('100000002', 'two@example.com');
        $this->post('/dashboard/corporate/store', $second)->assertRedirect();

        $passwords = [];
        Mail::assertSent(TemporaryPasswordMail::class, function ($mail) use (&$passwords) {
            $passwords[] = $mail->temporaryPassword;
            return strlen($mail->temporaryPassword) >= 16;
        });
        $this->assertCount(2, $passwords);
        $this->assertNotSame($passwords[0], $passwords[1]);
        $this->assertNotSame('100000001', $passwords[0]);

        $client = CorporateClient::query()->where('email', 'one@example.com')->first();
        $this->assertTrue($client->password_must_change);
        $this->assertTrue(Hash::check($passwords[0], $client->password));
        $hash = $client->password;

        $this->post('/dashboard/corporate/'.$client->id.'/save', $this->corporatePayload('100000009', 'one@example.com'))
            ->assertRedirect();
        $this->assertSame($hash, $client->fresh()->password);

        auth()->logout();
        $client->refresh();
        $this->post('/partner/login', ['username' => $client->username, 'password' => $passwords[0]])
            ->assertRedirect(route('partner.password'));
        $this->post('/partner/cars', [
            'plate' => 'AA123BB',
            'brand_id' => 1,
            'model_id' => 1,
        ])->assertRedirect(route('partner.password'));
        $this->assertSame(0, $client->cars()->count());

        $this->withHeader('X-Api-Key', $client->api_token)
            ->postJson('/api/corporate/vehicles', ['plate' => 'AB123CD', 'brand' => 'Toyota', 'model' => 'Camry'])
            ->assertForbidden();
        $this->assertSame(0, $client->cars()->count());

        $this->post('/partner/password', [
            'password' => 'changed-password',
            'password_confirmation' => 'changed-password',
        ])->assertRedirect(route('partner.home'));
        $client->refresh();
        $this->assertFalse($client->password_must_change);
        $this->assertFalse(Hash::check($passwords[0], $client->password));
        $this->post('/partner/logout');
        $this->post('/partner/login', ['username' => $client->username, 'password' => $passwords[0]])
            ->assertSessionHasErrors('username');

        $client->forceFill([
            'password' => 'expired-temp-password',
            'password_must_change' => true,
            'temp_password_expires_at' => now()->subHour(),
        ])->save();
        $this->post('/partner/login', ['username' => $client->username, 'password' => 'expired-temp-password'])
            ->assertSessionHasErrors('username');
    }

    public function test_failed_temporary_password_email_does_not_open_the_account(): void
    {
        $admin = $this->customer('555161616', 'adminpass', User::ROLE_ADMIN);
        $this->actingAs($admin);
        \Illuminate\Support\Facades\Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('smtp down'));

        $before = CorporateClient::query()->count();
        $this->post('/dashboard/corporate/store', $this->corporatePayload('100000003', 'fail@example.com'));

        $this->assertSame($before, CorporateClient::query()->count());
        $this->assertNull(CorporateClient::query()->where('email', 'fail@example.com')->first());
    }

    public function test_partner_voucher_cycle_requires_partner_auth(): void
    {
        $admin = $this->customer('555202020', 'adminpass', User::ROLE_ADMIN);
        $this->actingAs($admin);
        Mail::fake();
        $this->post('/dashboard/partners/store', [
            'name' => 'Voucher shop',
            'description' => 'A partner shop',
            'login' => 'shop-login',
            'email' => 'shop@example.com',
        ])->assertRedirect();
        $plain = null;
        Mail::assertSent(TemporaryPasswordMail::class, function ($mail) use (&$plain) {
            $plain = $mail->temporaryPassword;
            return $mail->login === 'shop-login';
        });
        $other = null;
        $this->post('/dashboard/partners/store', [
            'name' => 'Second shop',
            'description' => 'Another partner',
            'login' => 'shop-two',
            'email' => 'shop2@example.com',
        ]);
        Mail::assertSent(TemporaryPasswordMail::class, function ($mail) use (&$other) {
            if ($mail->login === 'shop-two') {
                $other = $mail->temporaryPassword;
            }
            return true;
        });
        $this->assertNotSame($plain, $other);
        auth()->logout();

        $owner = $this->customer('555303030', 'secret12');
        $category = VoucherCategory::query()->create(['name' => 'Fuel']);
        $voucher = Voucher::query()->create([
            'name' => 'Fuel',
            'description' => 'Fuel voucher',
            'photo' => 'fuel.png',
            'category_id' => $category->id,
            'price' => 10,
        ]);
        $userVoucher = UserVoucher::query()->create([
            'user_id' => $owner->id,
            'voucher_id' => $voucher->id,
            'code' => 'AB12CD34',
        ]);

        $this->postJson('/api/partner/voucher/check', ['code' => 'AB12CD34'])->assertUnauthorized();
        $this->assertDatabaseHas('user_vouchers', ['id' => $userVoucher->id]);

        $userToken = auth('api')->login($owner);
        $this->withToken($userToken)->postJson('/api/partner/voucher/check', ['code' => 'AB12CD34'])->assertUnauthorized();

        $this->withToken('not-a-jwt')->postJson('/api/partner/voucher/check', ['code' => 'AB12CD34'])->assertUnauthorized();

        $login = $this->postJson('/api/partner/login', ['login' => 'shop-login', 'password' => $plain])->assertOk();
        $this->assertTrue($login->json('must_change_password'));
        $limited = $login->json('access_token');
        $this->withToken($limited)->postJson('/api/partner/voucher/check', ['code' => 'AB12CD34'])
            ->assertForbidden()
            ->assertJsonPath('must_change_password', true);
        $this->assertDatabaseHas('user_vouchers', ['id' => $userVoucher->id]);

        $changed = $this->withToken($limited)->postJson('/api/partner/password', [
            'password' => 'permanent1',
            'password_confirmation' => 'permanent1',
        ])->assertOk();
        $this->assertFalse($changed->json('must_change_password'));
        $this->withToken($limited)->postJson('/api/partner/voucher/check', ['code' => 'AB12CD34'])->assertUnauthorized();

        $token = $changed->json('access_token');
        $check = $this->withToken($token)->postJson('/api/partner/voucher/check', ['code' => 'AB12CD34'])->assertOk();
        $sms = $this->sms()->latestCode();
        $this->assertStringNotContainsString($sms, $check->getContent());
        $temp = $check->json('temp_code');

        for ($i = 0; $i < 5; $i++) {
            $this->withToken($token)->postJson('/api/partner/voucher/use', [
                'temp_code' => $temp,
                'code' => '111111',
            ])->assertStatus(422);
        }
        $this->assertDatabaseHas('user_vouchers', ['id' => $userVoucher->id]);
        $this->withToken($token)->postJson('/api/partner/voucher/use', [
            'temp_code' => $temp,
            'code' => $sms,
        ])->assertStatus(422);

        $this->travel(11)->minutes();
        $check = $this->withToken($token)->postJson('/api/partner/voucher/check', ['code' => 'AB12CD34'])->assertOk();
        $sms = $this->sms()->latestCode();
        $this->withToken($token)->postJson('/api/partner/voucher/use', [
            'temp_code' => $check->json('temp_code'),
            'code' => $sms,
        ])->assertOk()->assertJsonPath('message', 'You successfully used voucher');
        $this->assertDatabaseMissing('user_vouchers', ['id' => $userVoucher->id]);
        $this->withToken($token)->postJson('/api/partner/voucher/use', [
            'temp_code' => $check->json('temp_code'),
            'code' => $sms,
        ])->assertStatus(422);

        $partner = Partner::query()->where('login', 'shop-login')->first();
        $partner->forceFill([
            'password' => 'expired-temp-password',
            'password_must_change' => true,
            'temp_password_expires_at' => now()->subHour(),
            'token_version' => (int) $partner->token_version + 1,
        ])->save();
        $this->postJson('/api/partner/login', [
            'login' => 'shop-login',
            'password' => 'expired-temp-password',
        ])->assertUnauthorized();
        $this->postJson('/api/partner/login', [
            'login' => 'shop-login',
            'password' => 'permanent1',
        ])->assertUnauthorized();
    }

    public function test_users_cannot_touch_another_package_and_managers_stay_on_their_branch(): void
    {
        $a = $this->customer('555404040', 'secret12');
        $b = $this->customer('555505050', 'secret12');
        $packageA = $this->packageFor($a);
        $packageB = $this->packageFor($b);

        $tokenA = auth('api')->login($a);
        $this->withToken($tokenA)->deleteJson('/api/packages/'.$packageB->id.'/remove')->assertForbidden();
        $this->assertDatabaseHas('user_packages', ['id' => $packageB->id]);
        $this->withToken($tokenA)->deleteJson('/api/packages/'.$packageA->id.'/remove')->assertOk();
        $this->assertDatabaseMissing('user_packages', ['id' => $packageA->id]);

        $managerA = $this->customer('555606060', 'secret12', User::ROLE_MANAGER);
        $managerB = $this->customer('555707070', 'secret12', User::ROLE_MANAGER);
        $washA = $this->wash($managerA, 'Branch A');
        $washB = $this->wash($managerB, 'Branch B');
        $car = $this->car($b);
        $appointment = Appointment::query()->create([
            'user_id' => $b->id,
            'car_wash_id' => $washB->id,
            'car_id' => $car->id,
            'date' => now()->addDay()->toDateString(),
            'time' => '10:00:00',
            'approved' => false,
            'qr_code' => 'appt-qr',
        ]);

        $this->withToken(auth('api')->login($managerA))
            ->postJson('/api/appointments/'.$appointment->id.'/edit', ['status' => 1])
            ->assertForbidden();
        $this->assertFalse((bool) $appointment->fresh()->approved);

        $active = $this->packageFor($b);
        $this->withToken(auth('api')->login($managerA))
            ->postJson('/api/package/'.$active->id.'/approve', ['car_wash_id' => $washB->id])
            ->assertForbidden();
        $this->assertSame(0, (int) $active->fresh()->used_washes);

        $this->withToken(auth('api')->login($managerA))
            ->postJson('/api/package/'.$active->id.'/approve')
            ->assertOk();
        $this->assertSame(1, (int) $active->fresh()->used_washes);

        $this->withToken(auth('api')->login($managerB))
            ->postJson('/api/appointments/'.$appointment->id.'/edit', ['status' => 1])
            ->assertOk();
        $this->assertEquals(1, $appointment->fresh()->approved);

        $qrPackage = $this->packageFor($b, 'QRCODE1234567890');
        $qr = $this->withToken(auth('api')->login($managerA))
            ->getJson('/api/checkqr/'.$qrPackage->qr_code)
            ->assertOk();
        $qr->assertJsonPath('package.user.name', $b->name);
        $qr->assertJsonMissingPath('package.user.email');
        $qr->assertJsonMissingPath('package.user.password');
        $qr->assertJsonMissingPath('package.user.points');
        $this->assertStringNotContainsString('referral', $qr->getContent());
    }

    public function test_panel_get_does_not_mutate_and_csrf_is_required(): void
    {
        $admin = $this->customer('555808080', 'adminpass', User::ROLE_ADMIN);
        $client = $this->customer('555909090', 'secret12');
        $this->actingAs($admin);

        $this->get('/dashboard/clients/'.$client->id.'/delete')->assertStatus(405);
        $this->assertDatabaseHas('users', ['id' => $client->id]);

        $middleware = new class(app(), app('encrypter')) extends \Illuminate\Foundation\Http\Middleware\PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        };
        $session = app('session.store');
        $session->start();
        $request = \Illuminate\Http\Request::create('/dashboard/clients/'.$client->id.'/delete', 'POST');
        $request->setLaravelSession($session);

        try {
            $middleware->handle($request, fn () => response('mutated'));
            $this->fail('A tokenless POST must be rejected.');
        } catch (\Illuminate\Session\TokenMismatchException) {
            $this->assertDatabaseHas('users', ['id' => $client->id]);
        }

        $allowed = \Illuminate\Http\Request::create('/dashboard/clients/'.$client->id.'/delete', 'POST', [
            '_token' => $session->token(),
        ]);
        $allowed->setLaravelSession($session);
        $passed = $middleware->handle($allowed, fn () => response('ok'));
        $this->assertSame(200, $passed->getStatusCode());

        $this->post('/dashboard/clients/'.$client->id.'/delete')
            ->assertRedirect(route('admin.clients'));
        $this->assertDatabaseMissing('users', ['id' => $client->id]);
    }

    public function test_payment_sandbox_is_limited_and_real_checks_still_hold(): void
    {
        $admin = $this->customer('555121212', 'adminpass', User::ROLE_ADMIN);
        $stranger = $this->customer('555131313', 'secret12');

        $this->withToken(auth('api')->login($stranger))->getJson('/api/testpay?amount=4')->assertForbidden();
        $this->assertSame(0, (int) $stranger->fresh()->points);
        $this->assertSame(0, TbcPayment::query()->count());

        $started = $this->withToken(auth('api')->login($admin))->getJson('/api/testpay?amount=4')->assertOk();
        $this->assertTrue($started->json('test_mode'));
        $query = [];
        parse_str((string) parse_url($started->json('url'), PHP_URL_QUERY), $query);

        $this->post('/payment/sandbox', [
            'order' => $query['order'],
            'decision' => 'pay',
            'method' => 'apple',
        ]);
        $this->assertSame(0, (int) $admin->fresh()->points);

        $this->post('/payment/sandbox', [
            'order' => $query['order'],
            'access' => $query['access'],
            'decision' => 'pay',
            'method' => 'apple',
        ])->assertRedirect();
        $this->assertSame(4, (int) $admin->fresh()->points);
        $this->post('/payment/sandbox', [
            'order' => $query['order'],
            'access' => $query['access'],
            'decision' => 'pay',
            'method' => 'apple',
        ])->assertRedirect();
        $this->assertSame(4, (int) $admin->fresh()->points);

        $real = TbcPayment::query()->create([
            'merchant_payment_id' => 'PREALPAYMENT0001',
            'user_id' => $stranger->id,
            'type' => 'points',
            'amount' => 8,
            'currency' => 'GEL',
            'status' => 'pending',
            'test_mode' => false,
            'payload' => ['points' => 8],
        ]);
        $this->post('/payment/sandbox', [
            'order' => $real->merchant_payment_id,
            'access' => 'guess',
            'decision' => 'pay',
            'method' => 'apple',
        ]);
        $this->assertSame(0, (int) $stranger->fresh()->points);
        $this->assertSame('pending', $real->fresh()->status);

        $flitt = \Mockery::mock(FlittCheckout::class);
        $flitt->shouldReceive('valid')->andReturn(true);
        $flitt->shouldReceive('minor')->andReturn(800);
        $this->app->instance(FlittCheckout::class, $flitt);
        $payload = ['order_id' => $real->merchant_payment_id, 'amount' => 800, 'order_status' => 'approved'];
        $this->postJson('/callback/flitt/payment', $payload)->assertOk();
        $this->postJson('/callback/flitt/payment', $payload)->assertOk();
        $this->assertSame(8, (int) $stranger->fresh()->points);
        $this->assertSame('succeeded', $real->fresh()->status);

        $mismatch = TbcPayment::query()->create([
            'merchant_payment_id' => 'PMISMATCH0000001',
            'user_id' => $stranger->id,
            'type' => 'points',
            'amount' => 3,
            'currency' => 'GEL',
            'status' => 'pending',
            'payload' => ['points' => 3],
        ]);
        $bad = \Mockery::mock(FlittCheckout::class);
        $bad->shouldReceive('valid')->andReturn(true);
        $bad->shouldReceive('minor')->andReturn(300);
        $this->app->instance(FlittCheckout::class, $bad);
        $this->postJson('/callback/flitt/payment', [
            'order_id' => $mismatch->merchant_payment_id,
            'amount' => 1,
            'order_status' => 'approved',
        ])->assertOk();
        $this->assertSame('amount_mismatch', $mismatch->fresh()->status);
        $this->assertSame(8, (int) $stranger->fresh()->points);

        $invalid = \Mockery::mock(FlittCheckout::class);
        $invalid->shouldReceive('valid')->andReturn(false);
        $this->app->instance(FlittCheckout::class, $invalid);
        $this->postJson('/callback/flitt/payment', ['order_id' => 'none'])->assertStatus(400);
    }

    public function test_guessable_passwords_are_retired_without_confirming_accounts(): void
    {
        $user = new User();
        $user->phone = '555141414';
        $user->password = '34rbb45i43jnv8493uh';
        $user->role = User::ROLE_USER;
        $user->save();
        $custom = new User();
        $custom->phone = '555151515';
        $custom->password = 'secret12';
        $custom->role = User::ROLE_USER;
        $custom->save();

        $corporate = new CorporateClient();
        $corporate->forceFill([
            'name' => 'Old Co',
            'username' => '100000111',
            'password' => '100000111',
            'credentials_custom' => false,
            'api_token' => 'token-old-co',
            'email' => 'old@example.com',
        ])->save();

        app(RetireGuessablePasswords::class)->handle();

        $user->refresh();
        $custom->refresh();
        $corporate->refresh();
        $this->assertFalse(Hash::check('34rbb45i43jnv8493uh', $user->password));
        $this->assertNull($user->phone_verified_at);
        $this->assertSame(1, (int) $user->token_version);
        $this->assertTrue(Hash::check('secret12', $custom->password));
        $this->assertNull($custom->phone_verified_at);
        $this->assertFalse(Hash::check('100000111', $corporate->password));
        $this->assertTrue($corporate->password_must_change);
        $this->assertNotNull($corporate->credentials_retired_at);
    }

    private function sms(): FakeSmsSender
    {
        return app(FakeSmsSender::class);
    }

    private function customer(string $phone, string $password, int $role = User::ROLE_USER): User
    {
        $user = new User();
        $user->name = 'Nino';
        $user->surname = 'Beridze';
        $user->phone = $phone;
        $user->password = $password;
        $user->role = $role;
        $user->phone_verified_at = now();
        $user->ref_code = substr(md5($phone.$role), 0, 5);
        $user->save();

        return $user->fresh();
    }

    private function corporatePayload(string $code, string $email): array
    {
        return [
            'name' => 'Company '.$code,
            'legal_form' => 'LLC',
            'identification_code' => $code,
            'legal_address' => 'Tbilisi',
            'actual_address' => 'Tbilisi',
            'bank_name' => 'TBC',
            'bank_code' => 'TBCBGE22',
            'bank_account' => 'GE00TB0000000000000001',
            'contact' => 'Contact',
            'phone' => '555000000',
            'email' => $email,
            'vat_payer' => 1,
        ];
    }

    private function packageFor(User $user, ?string $qr = null): UserPackage
    {
        $car = $this->car($user);
        $package = Package::query()->create(['car_type' => 'sedan', 'count_washes' => 5]);

        return UserPackage::query()->create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'user_car_id' => $car->id,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
            'number_of_washes' => 5,
            'used_washes' => 0,
            'qr_code' => $qr ?? bin2hex(random_bytes(8)),
        ]);
    }

    private function car(User $user): UserCar
    {
        $brand = CarBrand::query()->firstOrCreate(['name' => 'Toyota']);
        $model = CarModel::query()->firstOrCreate([
            'name' => 'Camry',
            'brand_id' => $brand->id,
        ], ['type' => 'sedan']);

        return UserCar::query()->create([
            'user_id' => $user->id,
            'model_id' => $model->id,
            'plate' => 'AA'.random_int(100, 999).'BB',
        ]);
    }

    private function wash(User $manager, string $name): CarWash
    {
        return CarWash::query()->create([
            'name' => $name,
            'address' => $name,
            'work_time_start' => '09:00:00',
            'work_time_end' => '18:00:00',
            'location' => '41.7,44.8',
            'manager_id' => $manager->id,
        ]);
    }
}
