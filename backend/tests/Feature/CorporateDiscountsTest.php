<?php

use App\Models\BodyType;
use App\Models\CorporateClient;
use App\Models\CorporatePackage;
use App\Models\CorporatePackagePrice;
use App\Models\CorporateWash;
use App\Models\CorporateWashMove;
use App\Services\CorporateWashes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/** A sedan company car in discount mode at the company's only discount package: sedan at 30 ₾ and 50%. */
function cdFleet(): array
{
    $fleet = cwFleet(10, 2);
    $fleet['package'] = cdPackage($fleet['corp'], 'ფასდაკლება');
    $fleet['price'] = $fleet['package']->prices()->create(['car_type' => 'sedan', 'full_price' => 30, 'percent' => 50]);
    CorporateWashes::setMode($fleet['car'], 'discount');

    return $fleet;
}

function cdPackage(CorporateClient $corp, string $title, bool $active = true): CorporatePackage
{
    $package = new CorporatePackage(['title' => $title, 'washes' => 0, 'is_active' => $active]);
    $package->forceFill(['corporate_client_id' => $corp->id, 'type' => 'discount'])->save();

    return $package;
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
        ->and($first->prices->pluck('car_type')->all())->toBe(['sedan']);

    // A company may have several active discount packages.
    $this->actingAs($admin)->post('/dashboard/packages/corporate/store', ['title' => 'ახალი', 'prices' => ['suv' => ['full_price' => 40, 'percent' => 25]]] + $form)
        ->assertSessionHasNoErrors();
    $second = CorporatePackage::query()->where('title', 'ახალი')->sole();
    expect($first->fresh()->is_active)->toBeTrue()
        ->and($corp->discountPackages()->pluck('title')->all())->toBe(['ახალი', 'ფასდაკლება'])
        ->and($second->prices->first()->toPay())->toBe('30.00');

    // Editing keeps the company and the type, and replaces the rows.
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
        ->and($corp->discountPackages()->pluck('id')->all())->toBe([$first->id]);
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

it('renames a body type without touching its price rows and keeps a used type from deletion', function () {
    $fleet = cdFleet();
    $admin = cwAdmin();
    $suv = BodyType::query()->where('name', 'suv')->firstOrFail();
    $fleet['package']->prices()->create(['car_type' => 'suv', 'full_price' => 40, 'percent' => 20]);

    $this->actingAs($admin)->post('/dashboard/body-types/'.$suv->id.'/save', ['name' => 'crossover', 'name_ka' => 'კროსოვერი', 'name_en' => 'Crossover'])->assertSessionHasNoErrors();
    expect($suv->fresh()->only('name', 'name_ka', 'name_en'))->toBe(['name' => 'suv', 'name_ka' => 'კროსოვერი', 'name_en' => 'Crossover'])
        ->and(CorporatePackagePrice::query()->where('car_type', 'suv')->exists())->toBeTrue();

    $this->actingAs($admin)->delete('/dashboard/body-types/'.$suv->id.'/delete')->assertSessionHas('error');
    expect(BodyType::query()->whereKey($suv->id)->exists())->toBeTrue();
});

it('lets the company give each discount car its own package', function () {
    $fleet = cdFleet();
    $corp = $fleet['corp'];
    $second = cwFleet(0, 0, 'BB222CC', $corp);
    $gold = cdPackage($corp, 'ოქრო');
    $gold->prices()->create(['car_type' => 'sedan', 'full_price' => 30, 'percent' => 80]);
    $off = cdPackage($corp, 'გამორთული', false);
    $foreign = cdPackage(CorporateClient::forceCreate(['name' => 'O', 'username' => 'o5', 'password' => Hash::make('x'), 'session_version' => 1]), 'სხვისი');
    $session = ['corporate_client_id' => $corp->id, 'corporate_session_version' => 1];

    // With two packages a car switched to discount waits for the company to pick one.
    $this->withSession($session)->post('/partner/cars/'.$second['car']->id.'/mode', ['mode' => 'discount'])->assertSessionHasNoErrors();
    expect($second['car']->fresh()->corporate_package_id)->toBeNull();
    $this->withSession($session)->get('/partner/fleet')->assertOk()
        ->assertSee(__('admin.cd_car_no_package'))->assertSee('ოქრო')->assertDontSee('გამორთული');
    $manager = cwManager();
    $qr = cwDriverQr($this, $second);
    $this->withToken(cwAs($manager))->getJson('/api/checkqr/'.$qr)->assertOk()
        ->assertJsonPath('corporate.reason', 'discount_not_ready');

    // Only the company's own active discount packages can be picked.
    foreach ([$off, $foreign] as $wrong) {
        $this->withSession($session)->post('/partner/cars/'.$second['car']->id.'/mode', ['mode' => 'discount', 'discount_package_id' => $wrong->id])
            ->assertSessionHasErrors('fleet');
    }
    $this->withSession($session)->post('/partner/cars/'.$second['car']->id.'/mode', ['mode' => 'discount', 'discount_package_id' => $gold->id])
        ->assertSessionHasNoErrors();

    // Each car washes at its own package.
    $qr = cwDriverQr($this, $second);
    $this->withToken(cwAs($manager))->getJson('/api/checkqr/'.$qr)->assertOk()
        ->assertJsonPath('corporate.can_approve', true)->assertJsonPath('corporate.discount.to_pay', 6);
    $qr = cwDriverQr($this, $fleet);
    $this->withToken(cwAs($manager))->getJson('/api/checkqr/'.$qr)->assertOk()
        ->assertJsonPath('corporate.discount.to_pay', 15);
    $this->withToken(cwAs($second['driver']))->getJson('/api/mycars')->assertJsonPath('cars.0.company_car.discount.percent', 80);
    $this->withSession($session)->get('/partner/washes')->assertOk()->assertSee(__('admin.cd_cars', ['count' => 1]));

    // Switching a package off stops its cars; washes mode drops the package.
    $gold->update(['is_active' => false]);
    $qr = cwDriverQr($this, $second);
    $this->withToken(cwAs($manager))->getJson('/api/checkqr/'.$qr)->assertOk()
        ->assertJsonPath('corporate.reason', 'discount_not_ready');
    $this->withSession($session)->post('/partner/cars/'.$second['car']->id.'/mode', ['mode' => 'washes'])->assertSessionHasNoErrors();
    expect($second['car']->fresh()->only(['mode', 'corporate_package_id']))->toBe(['mode' => 'washes', 'corporate_package_id' => null]);
});

it('lets the company API list discount packages and set one on a car', function () {
    $fleet = cdFleet();
    $key = CorporateClient::makeToken();
    $fleet['corp']->forceFill(['api_token' => $key])->save();
    $headers = ['X-Api-Key' => $key];
    $gold = cdPackage($fleet['corp'], 'ოქრო');
    $gold->prices()->create(['car_type' => 'sedan', 'full_price' => 30, 'percent' => 80]);
    $off = cdPackage($fleet['corp'], 'გამორთული', false);

    $this->getJson('/api/corporate/discounts', $headers)->assertOk()
        ->assertJsonCount(2, 'discount_packages')
        ->assertJsonPath('discount_packages.1.title', 'ფასდაკლება')
        ->assertJsonPath('discount_packages.1.prices.0.to_pay', 15);

    $this->patchJson('/api/corporate/vehicles/AA123BB', ['discount_package_id' => $off->id], $headers)->assertStatus(422);
    $this->patchJson('/api/corporate/vehicles/AA123BB', ['mode' => 'washes', 'discount_package_id' => $gold->id], $headers)->assertStatus(422);
    expect((int) $fleet['car']->fresh()->corporate_package_id)->toBe((int) $fleet['package']->id);

    $this->patchJson('/api/corporate/vehicles/AA123BB', ['discount_package_id' => $gold->id], $headers)->assertOk()
        ->assertJsonPath('mode', 'discount')->assertJsonPath('discount_package_id', $gold->id);
    $this->getJson('/api/corporate/vehicles', $headers)->assertJsonPath('vehicles.0.discount_package_id', $gold->id);

    // Back to discount mode without a package keeps the car's own one.
    $this->patchJson('/api/corporate/vehicles/AA123BB', ['mode' => 'discount'], $headers)->assertOk()
        ->assertJsonPath('discount_package_id', $gold->id);
});

it('caps the discount washes of each car per month when the package has a limit', function () {
    $this->travelTo(Carbon::parse('2026-10-30 12:00'));
    $fleet = cdFleet();
    $admin = cwAdmin();
    $manager = cwManager();
    $this->actingAs($admin)->post('/dashboard/packages/corporate/'.$fleet['package']->id.'/save', [
        'title' => 'ფასდაკლება', 'is_active' => 1, 'monthly_limit' => 2,
        'prices' => ['sedan' => ['full_price' => 30, 'percent' => 50]],
    ])->assertSessionHasNoErrors();
    expect($fleet['package']->fresh()->monthly_limit)->toBe(2);

    $approve = function () use ($fleet, $manager) {
        $qr = cwDriverQr($this, $fleet);
        $scan = $this->withToken(cwAs($manager))->getJson('/api/checkqr/'.$qr)->assertOk();
        if ($scan->json('approve_token')) {
            $this->withToken(cwAs($manager))->postJson('/api/corporate-wash/'.$fleet['car']->id.'/approve', ['approve_token' => $scan->json('approve_token')])->assertOk();
        }

        return $scan;
    };
    $approve()->assertJsonPath('corporate.discount.monthly_limit', 2)->assertJsonPath('corporate.discount.left', 2);
    $approve()->assertJsonPath('corporate.discount.left', 1);
    $approve()->assertJsonPath('corporate.can_approve', false)->assertJsonPath('corporate.reason', 'discount_limit')
        ->assertJsonPath('corporate.discount.left', 0);
    expect(CorporateWash::query()->count())->toBe(2);

    $session = ['corporate_client_id' => $fleet['corp']->id, 'corporate_session_version' => 1];
    $this->withSession($session)->get('/partner/fleet')->assertOk()->assertSee(__('admin.cd_car_month', ['used' => 2, 'limit' => 2]));
    $this->withSession($session)->get('/partner/washes')->assertOk()->assertSee(__('admin.cp_limit_value', ['count' => 2]));
    $this->withToken(cwAs($fleet['driver']))->getJson('/api/mycars')->assertJsonPath('cars.0.company_car.discount.left', 0);

    // The count restarts on the 1st.
    $this->travelTo(Carbon::parse('2026-11-01 09:00'));
    $approve()->assertJsonPath('corporate.can_approve', true)->assertJsonPath('corporate.discount.left', 2);

    // Unlimited: no cap in the payload, and a wash package never keeps a limit.
    $fleet['package']->update(['monthly_limit' => null]);
    $approve()->assertJsonPath('corporate.discount.monthly_limit', null)->assertJsonPath('corporate.discount.left', null);
    $this->actingAs($admin)->post('/dashboard/packages/corporate/store', [
        'corporate_client_id' => $fleet['corp']->id, 'type' => 'washes', 'title' => '10', 'washes' => 10, 'monthly_limit' => 3, 'is_active' => 1,
    ])->assertSessionHasNoErrors();
    expect(CorporatePackage::query()->where('type', 'washes')->sole()->monthly_limit)->toBeNull();
});
