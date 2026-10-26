<?php

use App\Models\BodyType;
use App\Models\CorporateClient;
use App\Models\CorporatePackage;
use App\Models\CorporatePackagePrice;
use App\Models\CorporateWash;
use App\Models\CorporateWashMove;
use App\Services\CorporateWashes;
use Illuminate\Support\Facades\Hash;

/** A sedan company car in discount mode; the company's active discount package has sedan at 30 ₾ and 50%. */
function cdFleet(): array
{
    $fleet = cwFleet(10, 2);
    CorporateWashes::setMode($fleet['car'], 'discount');
    $package = new CorporatePackage(['title' => 'ფასდაკლება', 'washes' => 0, 'is_active' => true]);
    $package->forceFill(['corporate_client_id' => $fleet['corp']->id, 'type' => 'discount'])->save();
    $fleet['package'] = $package;
    $fleet['price'] = $package->prices()->create(['car_type' => 'sedan', 'full_price' => 30, 'percent' => 50]);

    return $fleet;
}

it('rounds what the driver pays to the tetri', function () {
    expect(CorporatePackagePrice::price('30', 50))->toBe('15.00')
        ->and(CorporatePackagePrice::price('29.99', 33))->toBe('20.09')
        ->and(CorporatePackagePrice::price('25.50', 15))->toBe('21.68')
        ->and(CorporatePackagePrice::price('30', 100))->toBe('0.00');
});

it('lets the admin make a discount package for one company in the packages menu', function () {
    $corp = cwFleet()['corp'];
    $admin = cwAdmin();
    $form = ['corporate_client_id' => $corp->id, 'type' => 'discount', 'title' => 'ფასდაკლება', 'is_active' => 1];

    $this->actingAs($admin)->get('/dashboard/packages/corporate/add?type=discount&company='.$corp->id)->assertOk()
        ->assertSee('prices[sedan][full_price]', false);
    // A discount package needs at least one full row.
    $this->actingAs($admin)->post('/dashboard/packages/corporate/store', $form + ['prices' => ['sedan' => ['full_price' => '', 'percent' => '']]])
        ->assertSessionHasErrors('prices');
    $this->actingAs($admin)->post('/dashboard/packages/corporate/store', $form + ['prices' => ['sedan' => ['full_price' => 30, 'percent' => '']]])
        ->assertSessionHasErrors('prices.sedan.percent');
    $this->actingAs($admin)->post('/dashboard/packages/corporate/store', $form + ['prices' => ['tank' => ['full_price' => 30, 'percent' => 50]]])
        ->assertSessionHasErrors('prices');

    $this->actingAs($admin)->post('/dashboard/packages/corporate/store', $form + ['prices' => [
        'sedan' => ['full_price' => 30, 'percent' => 50], 'suv' => ['full_price' => '', 'percent' => ''],
    ]])->assertSessionHasNoErrors();
    $first = CorporatePackage::query()->sole();
    expect($first->type)->toBe('discount')->and($first->washes)->toBe(0)
        ->and($first->prices->pluck('car_type')->all())->toBe(['sedan'])
        ->and($corp->discountPackage->id)->toBe($first->id);

    // A second active discount package switches the first one off.
    $this->actingAs($admin)->post('/dashboard/packages/corporate/store', ['title' => 'ახალი', 'prices' => ['suv' => ['full_price' => 40, 'percent' => 25]]] + $form)
        ->assertSessionHasNoErrors();
    expect($first->fresh()->is_active)->toBeFalse()
        ->and($corp->fresh()->discountPackage->title)->toBe('ახალი')
        ->and($corp->fresh()->discountPackage->prices->first()->toPay())->toBe('30.00');

    // Editing keeps the company and the type, and replaces the rows.
    $second = $corp->fresh()->discountPackage;
    $other = CorporateClient::forceCreate(['name' => 'O', 'username' => 'o3', 'password' => Hash::make('x'), 'session_version' => 1]);
    $this->actingAs($admin)->post('/dashboard/packages/corporate/'.$second->id.'/save', [
        'corporate_client_id' => $other->id, 'type' => 'washes', 'title' => 'ახალი', 'is_active' => 1,
        'prices' => ['sedan' => ['full_price' => 20, 'percent' => 10]],
    ])->assertSessionHasNoErrors();
    $second->refresh();
    expect((int) $second->corporate_client_id)->toBe((int) $corp->id)->and($second->type)->toBe('discount')
        ->and($second->prices->pluck('car_type')->all())->toBe(['sedan']);

    $this->actingAs($admin)->get('/dashboard/packages/corporate/'.$second->id.'/edit')->assertOk()
        ->assertSee('Wash Co')->assertDontSee('name="corporate_client_id"', false);
    $this->actingAs($admin)->get('/dashboard/packages/corporate/add')->assertOk()
        ->assertSee('name="washes"', false)->assertDontSee('prices[sedan]', false);
    $this->actingAs($admin)->get('/dashboard/packages/corporate?company='.$corp->id)->assertOk()
        ->assertSee('ახალი')->assertSee('18.00 ₾');
    $this->actingAs($admin)->get('/dashboard/corporate/'.$corp->id)->assertOk()
        ->assertSee(__('admin.cd_title'))->assertSee('18.00 ₾')
        ->assertDontSee('/dashboard/corporate/'.$corp->id.'/packages');

    $this->actingAs($admin)->delete('/dashboard/packages/corporate/'.$second->id.'/delete')->assertRedirect();
    expect(CorporatePackagePrice::query()->where('corporate_package_id', $second->id)->exists())->toBeFalse()
        ->and($corp->fresh()->discountPackage)->toBeNull();
});

it('approves a discount car without touching the washes and records the price', function () {
    $fleet = cdFleet();
    $manager = cwManager();

    $this->withToken(cwAs($fleet['driver']))->getJson('/api/mycars')->assertOk()
        ->assertJsonPath('cars.0.company_car.mode', 'discount')
        ->assertJsonPath('cars.0.company_car.discount.percent', 50)
        ->assertJsonPath('cars.0.company_car.discount.to_pay', 15);

    $qr = cwDriverQr($this, $fleet);
    $scan = $this->withToken(cwAs($manager))->getJson('/api/checkqr/'.$qr)->assertOk()
        ->assertJsonPath('corporate.mode', 'discount')
        ->assertJsonPath('corporate.can_approve', true)
        ->assertJsonPath('corporate.discount.car_type', 'sedan')
        ->assertJsonPath('corporate.discount.full_price', 30)
        ->assertJsonPath('corporate.discount.percent', 50)
        ->assertJsonPath('corporate.discount.to_pay', 15);

    $this->withToken(cwAs($manager))->postJson('/api/corporate-wash/'.$fleet['car']->id.'/approve', ['approve_token' => $scan->json('approve_token')])
        ->assertOk()
        ->assertJsonPath('corporate.mode', 'discount')
        ->assertJsonPath('corporate.discount.to_pay', 15);

    expect($fleet['car']->fresh()->washes_left)->toBe(2)
        ->and($fleet['corp']->fresh()->washes_balance)->toBe(10)
        ->and(CorporateWashMove::query()->where('type', 'wash')->exists())->toBeFalse();
    $wash = CorporateWash::query()->sole();
    expect($wash->only(['mode', 'car_type', 'discount_percent']))->toBe(['mode' => 'discount', 'car_type' => 'sedan', 'discount_percent' => 50])
        ->and((int) $wash->corporate_package_id)->toBe((int) $fleet['package']->id)
        ->and((string) $wash->full_price)->toBe('30.00')
        ->and((string) $wash->to_pay)->toBe('15.00');

    // A later price change leaves the recorded wash as it was.
    $fleet['price']->update(['full_price' => 50]);
    expect((string) $wash->fresh()->to_pay)->toBe('15.00');
});

it('refuses a discount car when its body type has no row or the package is off', function () {
    $fleet = cdFleet();
    $fleet['price']->update(['car_type' => 'suv']);
    $manager = cwManager();
    $qr = cwDriverQr($this, $fleet);

    $this->withToken(cwAs($manager))->getJson('/api/checkqr/'.$qr)->assertOk()
        ->assertJsonPath('corporate.can_approve', false)
        ->assertJsonPath('corporate.reason', 'discount_not_ready')
        ->assertJsonPath('corporate.discount', null)
        ->assertJsonPath('approve_token', null);
    $this->withToken(cwAs($fleet['driver']))->getJson('/api/mycars')->assertJsonPath('cars.0.company_car.discount', null);

    // A grant from before the package was switched off is refused by the wash itself.
    $fleet['price']->update(['car_type' => 'sedan']);
    $token = $this->withToken(cwAs($manager))->getJson('/api/checkqr/'.$qr)->json('approve_token');
    $fleet['package']->update(['is_active' => false]);
    $this->withToken(cwAs($manager))->postJson('/api/corporate-wash/'.$fleet['car']->id.'/approve', ['approve_token' => $token])
        ->assertStatus(422)->assertJsonPath('reason', 'discount_not_ready');
    expect(CorporateWash::query()->exists())->toBeFalse();
});

it('shows the company its discount, each car price and the wash history read-only', function () {
    $fleet = cdFleet();
    $manager = cwManager();
    $qr = cwDriverQr($this, $fleet);
    $token = $this->withToken(cwAs($manager))->getJson('/api/checkqr/'.$qr)->json('approve_token');
    $this->withToken(cwAs($manager))->postJson('/api/corporate-wash/'.$fleet['car']->id.'/approve', ['approve_token' => $token])->assertOk();
    $session = ['corporate_client_id' => $fleet['corp']->id, 'corporate_session_version' => 1];

    $this->withSession($session)->get('/partner/washes')->assertOk()
        ->assertSee(__('admin.cd_title'))->assertSee(__('admin.ch_title'))
        ->assertSee('15.00 ₾')->assertSee('Vake')
        ->assertDontSee('/packages/corporate');
    $this->withSession($session)->get('/partner/fleet')->assertOk()
        ->assertSee(__('admin.cd_car_price', ['type' => 'sedan', 'percent' => 50, 'pay' => '15.00']));
});

it('renames the price rows with their body type and keeps a used type from deletion', function () {
    $fleet = cdFleet();
    $admin = cwAdmin();
    $suv = BodyType::query()->where('name', 'suv')->firstOrFail();
    $fleet['package']->prices()->create(['car_type' => 'suv', 'full_price' => 40, 'percent' => 20]);

    $this->actingAs($admin)->post('/dashboard/body-types/'.$suv->id.'/save', ['name' => 'crossover'])->assertSessionHasNoErrors();
    expect(CorporatePackagePrice::query()->where('car_type', 'crossover')->exists())->toBeTrue();

    $this->actingAs($admin)->delete('/dashboard/body-types/'.$suv->id.'/delete')->assertSessionHas('error');
    expect(BodyType::query()->whereKey($suv->id)->exists())->toBeTrue();
});
