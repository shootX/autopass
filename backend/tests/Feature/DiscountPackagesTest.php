<?php

use App\Models\BodyType;
use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\Package;
use App\Models\PackagePrice;
use App\Models\PackageWash;
use App\Models\TbcPayment;
use App\Models\User;
use App\Models\UserCar;
use App\Models\UserPackage;
use App\Services\Payments\FlittCheckout;
use App\Services\Payments\SettleTbcPayment;
use Illuminate\Testing\TestResponse;

/** A driver with a confirmed sedan, a sedan washes package and two sedan discount packages (unlimited, 3 washes). */
function discountFixture(string $phone = '555 72 00 01'): array
{
    $brand = CarBrand::query()->firstOrCreate(['name' => 'Toyota']);
    $model = CarModel::query()->forceCreate(['name' => 'Camry', 'type' => 'sedan', 'brand_id' => $brand->id]);
    $driver = makeUser($phone);
    $car = UserCar::query()->create(['user_id' => $driver->id, 'model_id' => $model->id, 'plate' => 'DP100DP',
        'car_type' => 'sedan', 'body_status' => 'confirmed']);

    $washes = Package::query()->create(['car_type' => 'sedan', 'count_washes' => 4]);
    PackagePrice::query()->create(['package_id' => $washes->id, 'month' => 1, 'price' => 40]);
    $unlimited = Package::query()->create(['car_type' => 'sedan', 'count_washes' => 0, 'type' => 'discount', 'full_price' => 30, 'percent' => 20]);
    PackagePrice::query()->create(['package_id' => $unlimited->id, 'month' => 1, 'price' => 25]);
    $limited = Package::query()->create(['car_type' => 'sedan', 'count_washes' => 3, 'type' => 'discount', 'full_price' => 30, 'percent' => 50]);
    PackagePrice::query()->create(['package_id' => $limited->id, 'month' => 1, 'price' => 10]);

    $checkout = Mockery::mock(FlittCheckout::class);
    $checkout->shouldReceive('checkoutUrl')->andReturn('https://checkout.example.test/discount');
    app()->instance(FlittCheckout::class, $checkout);

    return compact('driver', 'car', 'washes', 'unlimited', 'limited');
}

/** Buys the package through checkout and lands the payment, as the provider's callback does. */
function buyDiscount(array $f, Package $package): UserPackage
{
    test()->withToken(cwAs($f['driver']))->postJson('/api/packages/buy', ['car_id' => $f['car']->id, 'package_id' => $package->id, 'renewal' => false])
        ->assertOk()->assertJsonPath('success', true);
    $payment = TbcPayment::query()->where('payload->package_id', $package->id)->latest('id')->firstOrFail();
    app(SettleTbcPayment::class)->grantNow($payment);

    return UserPackage::query()->where('tbc_payment_id', $payment->id)->firstOrFail();
}

function scanAndApprove(User $manager, UserPackage $package): TestResponse
{
    $package->refresh();
    $token = test()->withToken(cwAs($manager))->getJson('/api/checkqr/'.$package->qr_code)->assertOk()->json('approve_token');

    return test()->withToken(cwAs($manager))->postJson("/api/package/{$package->id}/approve", ['approve_token' => $token]);
}

it('lists discount packages only for an app that asks for them', function () {
    $f = discountFixture();
    $token = cwAs($f['driver']);

    $this->withToken($token)->getJson('/api/packages?carid='.$f['car']->id)->assertOk()
        ->assertJsonCount(1, 'packages')->assertJsonPath('packages.0.type', 'washes');

    $list = $this->withToken($token)->getJson('/api/packages?discount=1&carid='.$f['car']->id)->assertOk()->json('packages');
    expect($list)->toHaveCount(3);
    $unlimited = collect($list)->firstWhere('id', $f['unlimited']->id);
    expect($unlimited['type'])->toBe('discount')
        ->and($unlimited['discount'])->toEqual(['full_price' => 30.0, 'percent' => 20, 'to_pay' => 24.0, 'unlimited' => true, 'days' => 30]);
});

it('sells a discount package for 30 days at the terms of checkout time', function () {
    $f = discountFixture('555 72 00 02');
    $this->freezeTime();

    $this->withToken(cwAs($f['driver']))->postJson('/api/packages/buy', ['car_id' => $f['car']->id, 'package_id' => $f['unlimited']->id, 'renewal' => false])->assertOk();
    $payment = TbcPayment::query()->latest('id')->firstOrFail();
    expect((float) $payment->amount)->toBe(25.0);
    // The admin changes the package before the payment lands; the driver keeps what was paid for.
    $f['unlimited']->update(['percent' => 5]);
    app(SettleTbcPayment::class)->grantNow($payment);

    $bought = UserPackage::query()->where('tbc_payment_id', $payment->id)->firstOrFail();
    expect($bought->unlimited)->toBeTrue()
        ->and($bought->discount_percent)->toBe(20)
        ->and($bought->toPay())->toBe('24.00')
        ->and($bought->end_date)->toEqual(now()->addDays(30)->toDateTimeString());

    // A second one of the same package can't be bought while it runs; another package can.
    $this->withToken(cwAs($f['driver']))->postJson('/api/packages/buy', ['car_id' => $f['car']->id, 'package_id' => $f['unlimited']->id, 'renewal' => false])->assertStatus(422);
    $this->withToken(cwAs($f['driver']))->postJson('/api/packages/buy', ['car_id' => $f['car']->id, 'package_id' => $f['washes']->id, 'sub_term' => 1, 'renewal' => false])->assertOk();
});

it('still sells a washes package by its washes count, never a discount one', function () {
    $f = discountFixture('555 72 00 03');
    Package::query()->whereKey($f['washes']->id)->delete();

    $this->withToken(cwAs($f['driver']))->postJson('/api/packages/buy', ['car_id' => $f['car']->id, 'number_of_washes' => 3, 'sub_term' => 1, 'renewal' => false])
        ->assertStatus(404);
    expect(TbcPayment::query()->count())->toBe(0);
});

it('shows the manager only what the driver pays, and records it on every wash of an unlimited package', function () {
    $f = discountFixture('555 72 00 04');
    $manager = cwManager('555 72 00 09');
    $bought = buyDiscount($f, $f['unlimited']);

    $scan = $this->withToken(cwAs($manager))->getJson('/api/checkqr/'.$bought->qr_code)->assertOk();
    expect($scan->json('package.kind'))->toBe('discount')
        ->and($scan->json('package.to_pay'))->toEqual(24)
        ->and($scan->json('package'))->not->toHaveKey('full_price');

    foreach (range(1, 5) as $_) {
        scanAndApprove($manager, $bought)->assertOk()->assertJsonPath('to_pay', 24);
    }
    expect($bought->fresh()->used_washes)->toBe(5)
        ->and(PackageWash::query()->where('user_package_id', $bought->id)->pluck('to_pay')->map(fn ($v) => (string) $v)->unique()->all())->toBe(['24.00']);

    $mine = $this->withToken(cwAs($f['driver']))->getJson('/api/packages/my')->assertOk()->json('packages.0');
    expect($mine['type'])->toBe('discount')->and($mine['unlimited'])->toBeTrue()
        ->and($mine['discount'])->toEqual(['full_price' => 30.0, 'percent' => 20, 'to_pay' => 24.0, 'saved' => 30.0]);
});

it('stops a limited discount package at its washes and when its 30 days end', function () {
    $f = discountFixture('555 72 00 05');
    $manager = cwManager('555 72 00 08');
    $bought = buyDiscount($f, $f['limited']);

    foreach (range(1, 3) as $_) {
        scanAndApprove($manager, $bought)->assertOk()->assertJsonPath('to_pay', 15);
    }
    scanAndApprove($manager, $bought)->assertStatus(422)->assertJsonPath('error', 'No washes left');
    expect($bought->fresh()->used_washes)->toBe(3);

    $unlimited = buyDiscount($f, $f['unlimited']);
    $this->travel(31)->days();
    scanAndApprove($manager, $unlimited)->assertStatus(422)->assertJsonPath('error', 'Package expired');
});

it('lets the admin make and edit a discount package', function () {
    $this->actingAs(cwAdmin());
    BodyType::query()->firstOrCreate(['name' => 'suv']);

    $this->post('/dashboard/packages/store', ['type' => 'discount', 'car_type' => 'suv', 'count_washes' => 0, 'full_price' => 40, 'percent' => 25, 'price_discount' => 30])
        ->assertSessionHasNoErrors();
    $package = Package::query()->where('car_type', 'suv')->firstOrFail();
    expect($package->only(['type', 'count_washes', 'percent']))->toBe(['type' => 'discount', 'count_washes' => 0, 'percent' => 25])
        ->and($package->prices()->pluck('price', 'month')->map(fn ($v) => (float) $v)->all())->toBe([1 => 30.0]);

    $this->get('/dashboard/packages')->assertOk()->assertSee('30.00');
    $this->get('/dashboard/packages/'.$package->id.'/edit')->assertOk();

    $this->post('/dashboard/packages/'.$package->id.'/edit', ['type' => 'discount', 'car_type' => 'suv', 'count_washes' => 4, 'full_price' => 40, 'percent' => 101, 'price_discount' => 30])
        ->assertSessionHasErrors('percent');
    $this->post('/dashboard/packages/'.$package->id.'/edit', ['type' => 'washes', 'car_type' => 'suv', 'count_washes' => 4,
        'price1' => 10, 'price2' => 25, 'price3' => 45, 'price4' => 80])->assertSessionHasNoErrors();
    expect($package->fresh()->only(['type', 'full_price', 'percent']))->toBe(['type' => 'washes', 'full_price' => null, 'percent' => null])
        ->and($package->prices()->count())->toBe(4);
});
