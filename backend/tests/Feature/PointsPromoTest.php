<?php

use App\Models\PromoCode;
use App\Models\TbcPayment;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Payments\FlittCheckout;
use App\Services\Payments\SettleTbcPayment;
use App\Services\Points\ReferralReward;
use App\Support\AppSettings;

function fakeFlitt(): void
{
    $flitt = Mockery::mock(FlittCheckout::class);
    $flitt->shouldReceive('checkoutUrl')->andReturn('https://pay.example/checkout');
    app()->instance(FlittCheckout::class, $flitt);
}

it('prices points by the admin price and discount', function () {
    AppSettings::store(['point_price' => 0.5, 'point_discount' => 10, 'points_min' => 5, 'points_max' => 200]);
    $user = makeUser('555940001');
    $token = auth('api')->login($user);

    $this->withToken($token)->getJson('/api/points/price')->assertOk()
        ->assertJsonPath('price.point_price', 0.5)
        ->assertJsonPath('price.discount_percent', 10)
        ->assertJsonPath('price.min', 5);

    $this->withToken($token)->postJson('/api/points/quote', ['amount' => 100])->assertOk()
        ->assertJsonPath('quote.subtotal', 50)
        ->assertJsonPath('quote.discount', 5)
        ->assertJsonPath('quote.total', 45);

    $this->withToken($token)->postJson('/api/points/quote', ['amount' => 2])->assertStatus(422);
    $this->withToken($token)->postJson('/api/points/quote', ['amount' => 500])->assertStatus(422);
});

it('charges the promo price and counts the code only after payment', function () {
    fakeFlitt();
    AppSettings::store(['point_price' => 1, 'point_discount' => 0]);
    PromoCode::query()->create(['code' => 'AUTUMN20', 'kind' => 'discount', 'value' => 20, 'max_uses' => 5]);
    $user = makeUser('555940002');
    $token = auth('api')->login($user);

    $this->withToken($token)->postJson('/api/points/buy', ['amount' => 50, 'promo_code' => 'autumn20'])->assertOk()
        ->assertJsonPath('quote.total', 40)
        ->assertJsonPath('quote.promo_percent', 20);

    $payment = TbcPayment::query()->latest('id')->firstOrFail();
    expect((float) $payment->amount)->toBe(40.0)
        ->and(PromoCode::query()->value('used_count'))->toBe(0);

    app(SettleTbcPayment::class)->grantNow($payment);
    expect((int) $user->fresh()->points)->toBe(50)
        ->and(PromoCode::query()->value('used_count'))->toBe(1);

    $this->withToken($token)->postJson('/api/points/quote', ['amount' => 50, 'promo_code' => 'AUTUMN20'])
        ->assertStatus(422)->assertJsonPath('code', 'promo_already_used');
});

it('activates a points code once per user and refuses expired codes', function () {
    PromoCode::query()->create(['code' => 'GIFT10', 'kind' => 'points', 'value' => 10]);
    PromoCode::query()->create(['code' => 'OLD', 'kind' => 'points', 'value' => 10, 'ends_at' => now()->subDay()]);
    PromoCode::query()->create(['code' => 'SALE', 'kind' => 'discount', 'value' => 15]);
    $user = makeUser('555940003');
    $token = auth('api')->login($user);

    $this->withToken($token)->postJson('/api/promo-codes/activate', ['code' => 'gift10'])->assertOk()
        ->assertJsonPath('kind', 'points')->assertJsonPath('points', 10);
    $this->withToken($token)->postJson('/api/promo-codes/activate', ['code' => 'GIFT10'])
        ->assertStatus(422)->assertJsonPath('code', 'promo_already_used');
    $this->withToken($token)->postJson('/api/promo-codes/activate', ['code' => 'OLD'])
        ->assertStatus(422)->assertJsonPath('code', 'promo_expired');
    $this->withToken($token)->postJson('/api/promo-codes/activate', ['code' => 'SALE'])->assertOk()
        ->assertJsonPath('kind', 'discount')->assertJsonPath('value', 15);

    expect((int) $user->fresh()->points)->toBe(10)
        ->and(Transaction::query()->where('user_id', $user->id)->where('system', 'Promo')->count())->toBe(1);
});

it('lets the admin set the price and make promo codes', function () {
    $admin = makeUser('555940004', User::ROLE_ADMIN);

    $this->actingAs($admin)->get('/dashboard/points')->assertOk();
    $this->actingAs($admin)->post('/dashboard/points', [
        'point_price' => '0.75', 'point_discount' => 5, 'points_min' => 1, 'points_max' => 500, 'referral_percent' => 10,
    ])->assertRedirect('/dashboard/points');
    expect(AppSettings::get('point_price'))->toBe(0.75)->and(AppSettings::get('referral_percent'))->toBe(10);

    $this->actingAs($admin)->post('/dashboard/points/codes', ['code' => 'new-1', 'kind' => 'discount', 'value' => 30])
        ->assertRedirect('/dashboard/points');
    $code = PromoCode::query()->where('code', 'NEW-1')->firstOrFail();
    $this->actingAs($admin)->post("/dashboard/points/codes/{$code->id}/toggle");
    expect($code->fresh()->active)->toBeFalse();
    $this->actingAs($admin)->post('/dashboard/points/codes', ['code' => 'BIG', 'kind' => 'discount', 'value' => 100])
        ->assertSessionHasErrors('value');

    $this->actingAs(makeUser('555940005', User::ROLE_MANAGER))->get('/dashboard/points')->assertForbidden();
});

it('pays the inviter the referral percent of a friend package, once', function () {
    AppSettings::store(['point_price' => 1, 'referral_percent' => 10]);
    $inviter = makeUser('555940006');
    $friend = makeUser('555940007');
    $friend->forceFill(['referral_id' => $inviter->id])->save();
    $payment = TbcPayment::query()->create(['merchant_payment_id' => 'PREF1', 'user_id' => $friend->id, 'type' => 'package',
        'amount' => 60, 'currency' => 'GEL', 'status' => 'succeeded', 'payload' => []]);

    expect(ReferralReward::forPayment($payment))->toBe(6)
        ->and(ReferralReward::forPayment($payment->fresh()))->toBe(0)
        ->and((int) $inviter->fresh()->points)->toBe(6);
});
