<?php

namespace Tests\Feature;

use App\Models\SmsDispatch;
use App\Models\User;
use App\Services\Sms\MockSmsGateway;
use App\Services\Sms\SmsConfigurationException;
use App\Services\Sms\SmsDispatcher;
use App\Services\Sms\SmsGateway;
use App\Services\Sms\SmsMessage;
use App\Services\Sms\SmsOfficeGateway;
use App\Services\Sms\SmsStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        app(MockSmsGateway::class)->reset();
    }

    public function test_mock_mode_does_not_use_the_network_and_hides_the_code(): void
    {
        Http::fake();

        $response = $this->postJson('/api/register', ['phone' => '555112200']);

        $response->assertOk()
            ->assertJsonPath('delivery', 'simulated')
            ->assertJsonPath('success', true);
        $code = app(MockSmsGateway::class)->latestCode();
        $this->assertNotNull($code);
        $this->assertStringNotContainsString($code, $response->getContent());
        $response->assertJsonMissingPath('sms_code');
        Http::assertNothingSent();

        $this->postJson('/api/register/verify', [
            'temp_code' => $response->json('temp_code'),
            'code' => $code,
        ])->assertOk()->assertJsonStructure(['setup_token']);
    }

    public function test_inbox_is_limited_to_an_enabled_mock_admin(): void
    {
        $this->postJson('/api/register', ['phone' => '555112201'])->assertOk();
        $code = app(MockSmsGateway::class)->latestCode();

        $this->getJson('/api/admin/sms-inbox')->assertUnauthorized();

        $user = $this->person('555112202', User::ROLE_USER);
        $this->withToken(auth('api')->login($user))->getJson('/api/admin/sms-inbox')->assertForbidden();

        $admin = $this->person('555112203', User::ROLE_ADMIN);
        $inbox = $this->withToken(auth('api')->login($admin))->getJson('/api/admin/sms-inbox');
        $inbox->assertOk();
        $this->assertStringContainsString('no-store', (string) $inbox->headers->get('Cache-Control'));
        $this->assertStringContainsString($code, $inbox->getContent());
        $this->assertStringContainsString('9955*****201', $inbox->json('messages.0.phone_mask'));
        $this->assertSame('რეგისტრაცია', $inbox->json('messages.0.purpose'));

        $page = $this->actingAs($admin)->get('/dashboard/sms-inbox')->assertOk();
        $this->assertStringContainsString('no-store', (string) $page->headers->get('Cache-Control'));
        $page->assertSee('SMS-ის სატესტო რეჟიმია', false);

        $this->travel(6)->minutes();
        $this->withToken(auth('api')->login($admin))->getJson('/api/admin/sms-inbox')
            ->assertOk()
            ->assertJsonPath('messages', []);

        Config::set('sms.mock_inbox_enabled', false);
        $this->withToken(auth('api')->login($admin))->getJson('/api/admin/sms-inbox')->assertNotFound();

        Config::set('sms.driver', 'smsoffice');
        Config::set('sms.mock_inbox_enabled', true);
        $this->withToken(auth('api')->login($admin))->getJson('/api/admin/sms-inbox')->assertNotFound();

        $this->actingAs($user)->get('/dashboard/sms-inbox')->assertForbidden();
    }

    public function test_codes_for_different_purposes_are_not_interchangeable(): void
    {
        $registered = $this->postJson('/api/register', ['phone' => '555112204'])->assertOk();
        $code = app(MockSmsGateway::class)->latestCode();
        $this->postJson('/api/register/set_password', [
            'setup_token' => 'temp-only',
            'password' => 'secret12',
        ])->assertStatus(422);

        $user = User::query()->where('phone', '555 11 22 04')->first();
        $user->password = 'secret12';
        $user->phone_verified_at = now();
        $user->save();

        $this->postJson('/api/change_password/verify', [
            'temp_code' => $registered->json('temp_code'),
            'code' => $code,
        ])->assertStatus(422);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('secret12', $user->fresh()->password));

        $token = auth('api')->login($user->fresh());
        $this->withToken($token)->postJson('/api/change_phone/verify', [
            'temp_code' => $registered->json('temp_code'),
            'code' => $code,
            'phone' => '555112205',
        ])->assertStatus(422);
        $this->assertSame('555 11 22 04', $user->fresh()->phone);
    }

    public function test_office_gateway_posts_form_data_and_classifies_responses(): void
    {
        Config::set('sms.driver', 'smsoffice');
        Config::set('sms.office.key', 'office-key');
        Config::set('sms.office.sender', 'AutoPass');
        Config::set('sms.office.urgent', false);
        $this->app->forgetInstance(SmsGateway::class);

        Http::fake(function ($request) {
            $reference = $request->data()['reference'] ?? '';

            return match ($reference) {
                'ref22345678901234567' => Http::response([
                    'Success' => false,
                    'Message' => 'balance',
                    'Output' => null,
                    'ErrorCode' => 20,
                ], 200),
                'ref32345678901234567' => Http::response('not-json', 200),
                'ref42345678901234567' => throw new ConnectionException('timed out'),
                default => Http::response([
                    'Success' => true,
                    'Message' => 'ok',
                    'Output' => [],
                    'ErrorCode' => 0,
                ], 200),
            };
        });

        $result = app(SmsGateway::class)->send($this->message('995555112233', 'კოდი 482913'));
        $this->assertSame(SmsStatus::Accepted, $result->status);
        $this->assertNotSame('delivered', $result->status->value);

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && $request->url() === 'https://smsoffice.ge/api/v2/send/'
                && ! str_contains($request->url(), 'office-key')
                && $request['destination'] === '995555112233'
                && $request['sender'] === 'AutoPass'
                && $request['content'] === 'კოდი 482913'
                && $request['reference'] === 'ref12345678901234567'
                && ! isset($request['urgent']);
        });

        $failed = app(SmsGateway::class)->send($this->message('995555112233', 'კოდი 482913', 'ref22345678901234567'));
        $this->assertSame(SmsStatus::Failed, $failed->status);
        $this->assertSame(20, $failed->errorCode);

        $this->assertSame(
            SmsStatus::Failed,
            app(SmsGateway::class)->send($this->message('995555112233', 'კოდი', 'ref32345678901234567'))->status
        );

        $unknown = app(SmsGateway::class)->send($this->message('995555112233', 'კოდი', 'ref42345678901234567'));
        $this->assertSame(SmsStatus::Unknown, $unknown->status);

        Config::set('sms.office.key', '');
        $this->app->forgetInstance(SmsGateway::class);
        Http::fake();
        $missing = app(SmsOfficeGateway::class)->send($this->message('995555112233', 'კოდი', 'ref52345678901234567'));
        $this->assertSame(SmsStatus::Failed, $missing->status);
        $this->assertSame('missing_key', $missing->reason);
        Http::assertNothingSent();
    }

    public function test_timeout_is_not_retried_and_one_reference_is_sent_once(): void
    {
        Config::set('sms.driver', 'smsoffice');
        Config::set('sms.office.key', 'office-key');
        Config::set('sms.office.sender', 'AutoPass');
        $this->app->forgetInstance(SmsGateway::class);

        Http::fake();
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;
            throw new ConnectionException('timed out');
        });

        $message = $this->message('995555112233', 'კოდი 111111', 'ref62345678901234567');
        $dispatcher = new SmsDispatcher(app(SmsGateway::class));
        $first = $dispatcher->send($message);
        $second = $dispatcher->send($message);

        $this->assertSame(SmsStatus::Unknown, $first->status);
        $this->assertSame(SmsStatus::Unknown, $second->status);
        $this->assertSame(1, SmsDispatch::query()->count());
        $this->assertSame(1, $calls);
    }

    public function test_unknown_driver_is_a_configuration_error(): void
    {
        Config::set('sms.driver', 'other');
        $this->app->forgetInstance(SmsGateway::class);

        $this->expectException(SmsConfigurationException::class);
        app(SmsGateway::class);
    }

    public function test_georgian_destination_formats_normalize_to_one_number(): void
    {
        $this->assertSame('995555112233', \App\Support\Phone::forSms('+995 555 11 22 33'));
        $this->assertSame('995555112233', \App\Support\Phone::forSms('555112233'));
    }

    private function person(string $phone, int $role): User
    {
        $user = new User();
        $user->name = 'Test';
        $user->phone = $phone;
        $user->password = 'secret12';
        $user->role = $role;
        $user->phone_verified_at = now();
        $user->save();

        return $user->fresh();
    }

    private function message(string $destination, string $content, string $reference = 'ref12345678901234567'): SmsMessage
    {
        return new SmsMessage($destination, $content, 'verify', $reference);
    }
}
