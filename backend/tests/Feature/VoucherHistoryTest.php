<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\User;
use App\Models\UserVoucher;
use App\Models\Voucher;
use App\Models\VoucherCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoucherHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_lists_own_vouchers_with_status_newest_first(): void
    {
        $partner = Partner::query()->forceCreate([
            'name' => 'Shop', 'description' => 'd', 'login' => 'shop-history',
            'email' => 'history@example.com', 'password' => bcrypt('secret12'), 'password_must_change' => false,
        ]);
        $user = $this->user('555101010', 'hi123');
        $other = $this->user('555202020', 'hi456');
        $category = VoucherCategory::query()->create(['name' => 'Fuel']);
        $live = Voucher::query()->create(['name' => 'Fuel -10%', 'description' => 'x', 'photo' => 'f.png',
            'category_id' => $category->id, 'price' => 10, 'percent' => 10]);
        $live->forceFill(['partner_id' => $partner->id])->save();
        $gone = Voucher::query()->create(['name' => 'Old', 'description' => 'x', 'photo' => 'o.png',
            'category_id' => $category->id, 'price' => 5]);
        $gone->forceFill(['deleted' => true])->save();

        UserVoucher::query()->create(['user_id' => $user->id, 'voucher_id' => $live->id, 'code' => 'USED0001'])
            ->forceFill(['used_at' => now(), 'used_by_partner_id' => $partner->id])->save();
        UserVoucher::query()->create(['user_id' => $user->id, 'voucher_id' => $gone->id, 'code' => 'GONE0001']);
        UserVoucher::query()->create(['user_id' => $user->id, 'voucher_id' => $live->id, 'code' => 'LIVE0001']);
        UserVoucher::query()->create(['user_id' => $other->id, 'voucher_id' => $live->id, 'code' => 'OTHR0001']);
        $past = Voucher::query()->create(['name' => 'Past', 'description' => 'x', 'photo' => 'p.png',
            'category_id' => $category->id, 'price' => 5, 'date_to' => now()->subDay()->toDateString()]);
        UserVoucher::query()->create(['user_id' => $user->id, 'voucher_id' => $past->id, 'code' => 'PAST0001']);

        $rows = $this->withToken(auth('api')->login($user))->getJson('/api/vouchers/history')
            ->assertOk()->assertJsonPath('success', true)->json('vouchers');

        // A disabled offer only stops selling: the one bought before still works until its last day.
        $this->assertSame(['PAST0001', 'LIVE0001', 'GONE0001', 'USED0001'], array_column($rows, 'code'));
        $this->assertSame(['expired', 'active', 'active', 'used'], array_column($rows, 'status'));
        $this->assertSame(now()->subDay()->toDateString(), $rows[0]['date_to']);
        $this->assertSame('Shop', $rows[1]['partner']);
        $this->assertSame('Fuel', $rows[1]['category']);
        $this->assertNull($rows[1]['used_at']);
        $this->assertNotNull($rows[3]['used_at']);
    }

    public function test_history_needs_a_login(): void
    {
        $this->getJson('/api/vouchers/history')->assertUnauthorized();
    }

    private function user(string $phone, string $ref): User
    {
        $user = new User;
        $user->forceFill(['name' => 'N', 'surname' => 'B', 'phone' => $phone, 'password' => 'secret12',
            'role' => User::ROLE_USER, 'phone_verified_at' => now(), 'ref_code' => $ref])->save();

        return $user;
    }
}
