<?php

use App\Models\CorporatePackage;
use App\Models\FleetCar;
use App\Models\Package;
use App\Models\PromoCode;
use App\Models\TbcPayment;
use App\Models\UserPackage;
use App\Services\CorporateQr;
use App\Services\FleetInvites;
use App\Services\Payments\FlittCheckout;
use App\Services\Payments\SettleTbcPayment;
use App\Services\Sms\MockSmsGateway;
use App\Support\AppSettings;
use App\Support\Phone;

it('preserves the active company link and QR when the same driver phone is saved again', function () {
    $f = cwFleet();
    $code = CorporateQr::issue($f['car']);
    $f['car']->forceFill(['invited_at' => now(), 'driver_consented_at' => now()])->save();
    $consent = $f['car']->driver_consented_at;

    expect(FleetInvites::setDriver($f['car'], $f['driver']->phone))->toBe('too_soon');
    expect($f['car']->fresh()->only(['status', 'user_car_id', 'qr_code']))->toBe([
        'status' => 'active', 'user_car_id' => $f['userCar']->id, 'qr_code' => $code,
    ])->and($f['car']->fresh()->driver_consented_at->eq($consent))->toBeTrue();
    $this->withToken(cwAs($f['driver']))->deleteJson('/api/mycars/'.$f['userCar']->id.'/remove')->assertForbidden();
});

it('hands the car over after saving the same phone and then a new phone before the old driver refreshes', function () {
    $f = cwFleet();
    FleetInvites::setDriver($f['car'], $f['driver']->phone);
    FleetInvites::setDriver($f['car'], '555 91 00 02');
    expect($f['userCar']->fresh()->trashed())->toBeTrue();
    $next = makeUser('555 91 00 02');
    $this->withToken(cwAs($next))->getJson('/api/me')->assertOk();
    expect($f['car']->fresh()->activation_error)->toBeNull()
        ->and((int) $f['userCar']->fresh()->user_id)->toBe((int) $next->id);
});

it('does not bypass the SMS cooldown through repeated phone saves or phone changes', function () {
    $f = cwFleet();
    app(MockSmsGateway::class)->reset();
    $f['car']->forceFill(['invited_at' => now()])->save();
    foreach (range(1, 3) as $attempt) {
        expect(FleetInvites::setDriver($f['car'], $f['driver']->phone))->toBe('too_soon');
    }
    expect(FleetInvites::setDriver($f['car'], '555 91 00 03'))->toBe('too_soon');
    expect(app(MockSmsGateway::class)->messages)->toHaveCount(0);
    $this->travel(61)->seconds();
    expect(FleetInvites::invite($f['car']->fresh()))->toBe('sent');
    expect(app(MockSmsGateway::class)->messages)->toHaveCount(1);
});

it('discovers a new invite through the invites endpoint without a preceding me call', function () {
    $f = cwFleet();
    $f['car']->forceFill(['status' => 'invited', 'user_car_id' => null, 'user_id' => null, 'activation_error' => null])->save();
    $this->withToken(cwAs($f['driver']))->getJson('/api/fleet-invites')->assertOk()
        ->assertJsonPath('invites.0.id', $f['car']->id);
    expect($f['car']->fresh()->activation_error)->toBe('driver_consent');
});

it('requires consent on migrated links and never moves the old driver car on a no', function () {
    $f = cwFleet();
    $migration = require database_path('migrations/2026_11_03_000008_require_consent_for_existing_fleet_links.php');
    $migration->down();
    $migration->up();
    expect($f['car']->fresh()->driver_consent_required)->toBeTrue();
    expect(FleetInvites::setDriver($f['car'], '555 91 00 04'))->toBe('driver_consent');
    expect($f['userCar']->fresh()->trashed())->toBeFalse();
    $this->withToken(cwAs($f['driver']))->getJson('/api/fleet-invites')->assertOk()->assertJsonCount(1, 'invites');
    $this->withToken(cwAs($f['driver']))->get('/api/mycars/'.$f['userCar']->id.'/company-qr')->assertNotFound();
    $this->withToken(cwAs($f['driver']))->postJson('/api/fleet-invites/'.$f['car']->id.'/decline')->assertOk();
    expect($f['car']->fresh()->user_car_id)->toBeNull()
        ->and((int) $f['userCar']->fresh()->user_id)->toBe((int) $f['driver']->id)
        ->and($f['userCar']->fresh()->trashed())->toBeFalse();
});

it('lets the legacy driver explicitly accept and only then permits a handover', function () {
    $f = cwFleet();
    $f['car']->forceFill(['driver_consent_required' => true])->save();
    $this->withToken(cwAs($f['driver']))->postJson('/api/fleet-invites/'.$f['car']->id.'/accept')->assertOk();
    expect($f['car']->fresh()->driver_consent_required)->toBeFalse()
        ->and($f['car']->fresh()->driver_consented_at)->not->toBeNull();
    FleetInvites::setDriver($f['car']->fresh(), '555 91 00 05');
    expect($f['userCar']->fresh()->trashed())->toBeTrue();
});

it('waits for the driver to pick an ambiguous company body and assigns no discount package before it', function () {
    fakeYearCatalog();
    $f = cwFleet();
    $key = ['X-Api-Key' => $f['corp']->api_token];
    $this->postJson('/api/corporate/vehicles', ['plate' => 'AF101AF', 'brand' => 'Toyota', 'model' => 'Avensis',
        'year' => 2016, 'body_type' => 'wagon', 'phone' => $f['driver']->phone], $key)
        ->assertOk()->assertJsonPath('pending.0.body_status', 'choose');
    $car = FleetCar::query()->where('plate', 'AF101AF')->sole();
    $this->withToken(cwAs($f['driver']))->getJson('/api/me')->assertOk();
    $car->refresh();
    $this->withToken(cwAs($f['driver']))->get('/api/mycars/'.$car->user_car_id.'/company-qr')->assertForbidden();
    $package = CorporatePackage::query()->forceCreate(['corporate_client_id' => $f['corp']->id, 'title' => 'Discount',
        'type' => 'discount', 'washes' => 0, 'is_active' => true]);
    $this->withoutToken()->patchJson('/api/corporate/vehicles/AF101AF', ['discount_package_id' => $package->id], $key)->assertStatus(422);
    expect($car->fresh()->corporate_package_id)->toBeNull();
    $this->withToken(cwAs($f['driver']))->postJson('/api/mycars/'.$car->user_car_id.'/body', ['car_type' => 'sedan'])->assertOk();
    expect($car->fresh()->car_type)->toBe('sedan')->and($car->fresh()->body_source)->toBe('driver');
});

it('rejects a stale yes or no after the company changed the invited phone', function () {
    $f = cwFleet();
    $f['car']->forceFill(['status' => 'invited', 'user_car_id' => null, 'user_id' => null, 'activation_error' => 'driver_consent'])->save();
    $stale = $f['car']->fresh();
    FleetInvites::setDriver($f['car'], '555 91 00 09');

    expect(FleetInvites::accept($stale, $f['driver']))->toBe('invite_gone')
        ->and(FleetInvites::decline($stale, $f['driver']))->toBeFalse()
        ->and($f['car']->fresh()->driver_consented_at)->toBeNull()
        ->and($f['car']->fresh()->driver_phone)->toBe(Phone::normalize('555 91 00 09'));
});

it('reopens an old company-only body choice without losing paid packages or wash balances', function () {
    $f = cwFleet(10, 3);
    $body = ['body_source' => 'company', 'body_options' => ['sedan', 'wagon']];
    $f['car']->forceFill($body)->save();
    $f['userCar']->forceFill($body)->save();
    $package = UserPackage::query()->create(['user_id' => $f['driver']->id, 'user_car_id' => $f['userCar']->id,
        'package_id' => Package::create(['car_type' => 'sedan', 'count_washes' => 4])->id,
        'end_date' => now()->addMonth(), 'number_of_washes' => 4, 'used_washes' => 1, 'qr_code' => 'old-company-code']);
    $package->forceFill(['body_legacy' => true, 'qr_expires_at' => now()->addMinutes(5)])->save();
    $migration = require database_path('migrations/2026_11_03_000009_require_driver_pick_for_ambiguous_company_bodies.php');
    $migration->up();
    expect($f['car']->fresh()->body_status)->toBe('choose')->and($f['car']->fresh()->washes_left)->toBe(3)
        ->and($f['corp']->fresh()->washes_balance)->toBe(10)->and($package->fresh()->used_washes)->toBe(1)
        ->and($package->fresh()->bodyAllowsWash())->toBeFalse()->and($package->fresh()->qr_expires_at)->toBeNull();
});

it('keeps a promo reserved past two hours until the provider gives a final answer', function () {
    $flitt = Mockery::mock(FlittCheckout::class)->makePartial();
    $flitt->shouldReceive('checkoutUrl')->andReturn('https://pay.example/test');
    app()->instance(FlittCheckout::class, $flitt);
    AppSettings::store(['point_price' => 1, 'point_discount' => 0]);
    PromoCode::create(['code' => 'HOLD', 'kind' => 'discount', 'value' => 50, 'max_uses' => 1]);
    $a = makeUser('555 91 00 06');
    $b = makeUser('555 91 00 07');
    $this->withToken(cwAs($a))->postJson('/api/points/buy', ['amount' => 50, 'promo_code' => 'HOLD'])->assertOk();
    $payment = TbcPayment::query()->sole();
    $this->travel(121)->minutes();
    $this->withToken(cwAs($b))->postJson('/api/points/buy', ['amount' => 50, 'promo_code' => 'HOLD'])->assertStatus(422);
    app(SettleTbcPayment::class)->fromFlitt(['order_id' => $payment->merchant_payment_id,
        'order_status' => 'approved', 'amount' => $flitt->minor($payment)], $flitt);
    expect(PromoCode::query()->sole()->used_count)->toBe(1)->and(TbcPayment::query()->count())->toBe(1);
});

it('refuses a paid package for a different confirmed body without consuming its washes', function () {
    $f = cwFleet();
    $package = UserPackage::create(['user_id' => $f['driver']->id, 'user_car_id' => $f['userCar']->id,
        'package_id' => Package::create(['car_type' => 'wagon', 'count_washes' => 4])->id,
        'end_date' => now()->addMonth(), 'number_of_washes' => 4, 'used_washes' => 0, 'qr_code' => 'wrong-body-code']);
    $this->withToken(cwAs($f['driver']))->get('/api/packages/'.$package->id.'/qr')->assertStatus(422)
        ->assertJsonPath('error_code', 'package_body_mismatch');
    expect($package->fresh()->used_washes)->toBe(0)->and($package->fresh()->trashed())->toBeFalse();
});

it('preserves an explicit matching driver body confirmation during the company body migration', function () {
    $f = cwFleet();
    $f['car']->forceFill(['body_source' => 'company', 'body_options' => ['sedan', 'wagon']])->save();
    $f['userCar']->forceFill(['body_source' => 'driver'])->save();
    $migration = require database_path('migrations/2026_11_03_000009_require_driver_pick_for_ambiguous_company_bodies.php');
    $migration->up();
    expect($f['car']->fresh()->body_status)->toBe('confirmed')
        ->and($f['car']->fresh()->body_source)->toBe('driver');
});

it('keeps the purchased category when the catalogue changes while a payment is open', function () {
    $f = cwFleet();
    $plan = Package::create(['car_type' => 'sedan', 'count_washes' => 4]);
    $price = $plan->prices()->create(['month' => 1, 'price' => 40]);
    $flitt = Mockery::mock(FlittCheckout::class)->makePartial();
    $flitt->shouldReceive('checkoutUrl')->andReturn('https://pay.example/snapshot');
    app()->instance(FlittCheckout::class, $flitt);
    $this->withToken(cwAs($f['driver']))->postJson('/api/packages/buy', ['car_id' => $f['userCar']->id,
        'package_id' => $plan->id, 'sub_term' => 1, 'renewal' => false])->assertOk();
    $payment = TbcPayment::query()->sole();
    expect($payment->payload['body_type'])->toBe('sedan');
    $plan->update(['car_type' => 'wagon']);
    app(SettleTbcPayment::class)->grantNow($payment);
    $bought = UserPackage::query()->where('tbc_payment_id', $payment->id)->sole();
    expect($bought->body_type)->toBe('sedan')->and($bought->bodyAllowsWash())->toBeTrue();
});
