<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\SmsTemp;
use App\Models\User;
use App\Models\UserVoucher;
use App\Models\Voucher;
use App\Models\VoucherCategory;
use App\Services\Sms\MockSmsGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class VoucherSmsDedupeTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        app(MockSmsGateway::class)->reset();
        $this->withoutMiddleware(ThrottleRequests::class);

        $partner = Partner::query()->forceCreate([
            'name' => 'Shop', 'description' => 'd', 'login' => 'shop-dedupe',
            'email' => 'dedupe@example.com', 'password' => bcrypt('secret12'), 'password_must_change' => false,
        ]);
        $owner = new User;
        $owner->forceFill(['name' => 'N', 'surname' => 'B', 'phone' => '555404040', 'password' => 'secret12',
            'role' => User::ROLE_USER, 'phone_verified_at' => now(), 'ref_code' => 'dd123'])->save();
        $category = VoucherCategory::query()->create(['name' => 'Fuel']);
        $voucher = Voucher::query()->create(['name' => 'Fuel', 'description' => 'x', 'photo' => 'f.png',
            'category_id' => $category->id, 'price' => 10]);
        $voucher->forceFill(['partner_id' => $partner->id])->save();
        UserVoucher::query()->create(['user_id' => $owner->id, 'voucher_id' => $voucher->id, 'code' => 'ZX12CV34']);

        $this->token = $this->postJson('/api/partner/login', ['login' => 'shop-dedupe', 'password' => 'secret12'])
            ->assertOk()->json('access_token');
    }

    public function test_two_rapid_identical_checks_send_one_sms_and_share_the_challenge(): void
    {
        $first = $this->withToken($this->token)->postJson('/api/partner/voucher/check', ['code' => 'ZX12CV34'])->assertOk();
        $second = $this->withToken($this->token)->postJson('/api/partner/voucher/check', ['code' => 'ZX12CV34'])->assertOk();

        $this->assertCount(1, app(MockSmsGateway::class)->messages);
        $this->assertSame(1, SmsTemp::query()->where('type', 'use_voucher')->count());
        $this->assertSame($first->json('temp_code'), $second->json('temp_code'));
        $second->assertJsonPath('deduplicated', true);
    }

    public function test_a_check_racing_an_in_flight_check_does_not_send_a_second_sms(): void
    {
        $voucherId = UserVoucher::query()->where('code', 'ZX12CV34')->value('id');
        $userId = UserVoucher::query()->whereKey($voucherId)->value('user_id');
        // Simulate the parallel request still holding the dedupe lock for this voucher+recipient.
        $lock = Cache::lock('voucher-check:'.$voucherId.':'.$userId, 10);
        $this->assertTrue($lock->get());

        $this->withToken($this->token)->postJson('/api/partner/voucher/check', ['code' => 'ZX12CV34'])
            ->assertStatus(429)->assertJsonPath('success', false);
        $this->assertCount(0, app(MockSmsGateway::class)->messages);
        $lock->release();
    }

    public function test_after_the_window_a_new_sms_is_sent(): void
    {
        $this->withToken($this->token)->postJson('/api/partner/voucher/check', ['code' => 'ZX12CV34'])->assertOk();
        $this->travel(61)->seconds();
        $this->withToken($this->token)->postJson('/api/partner/voucher/check', ['code' => 'ZX12CV34'])->assertOk();

        $this->assertCount(2, app(MockSmsGateway::class)->messages);
    }
}
