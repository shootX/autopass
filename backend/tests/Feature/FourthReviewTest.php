<?php

// Fourth external review (2026-11-03): a company can't take a driver's own car without their yes, a closed promo
// page is asked about before a new one, a late package follows its car, deletion voids live codes, erasing clears
// every VIN, and the open QR screen checks the code it shows.

use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\CorporateClient;
use App\Models\FleetCar;
use App\Models\Package;
use App\Models\PackagePrice;
use App\Models\PromoCode;
use App\Models\TbcPayment;
use App\Models\User;
use App\Models\UserCar;
use App\Models\UserPackage;
use App\Services\FleetInvites;
use App\Services\Payments\FlittCheckout;
use App\Services\Payments\SettleTbcPayment;
use App\Support\AppSettings;
use App\Support\Phone;
use App\Support\QrCodeId;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery\MockInterface;

function frModel(): CarModel
{
    $brand = CarBrand::query()->firstOrCreate(['name' => 'Toyota']);

    return CarModel::query()->where('name', 'Camry')->first()
        ?? CarModel::query()->forceCreate(['name' => 'Camry', 'type' => 'sedan', 'brand_id' => $brand->id]);
}

function frCompany(): CorporateClient
{
    return CorporateClient::forceCreate([
        'name' => 'Taxi Co', 'api_token' => CorporateClient::makeToken(), 'username' => 'taxico'.Str::random(4),
        'password' => Hash::make('corppass1'), 'password_must_change' => false, 'session_version' => 1,
    ]);
}

/** A company car for $phone with $plate, invited, its body confirmed as a sedan. */
function frFleetCar(CorporateClient $company, string $plate, string $phone): FleetCar
{
    $car = FleetCar::query()->create(['corporate_client_id' => $company->id, 'plate' => $plate, 'brand' => 'Toyota', 'model' => 'Camry', 'source' => 'manual']);
    $car->forceFill(['status' => FleetCar::STATUS_INVITED, 'driver_phone' => Phone::normalize($phone), 'car_type' => 'sedan', 'body_status' => 'confirmed', 'year' => 2020])->save();

    return $car;
}

/** A driver with their own sedan and a running package on it. */
function frDriver(string $phone, string $plate): array
{
    $driver = makeUser($phone);
    $car = UserCar::query()->create(['user_id' => $driver->id, 'model_id' => frModel()->id, 'plate' => $plate,
        'car_type' => 'sedan', 'body_status' => 'confirmed', 'vin' => 'JT'.strtoupper(Str::random(15))]);
    $package = Package::query()->firstOrCreate(['car_type' => 'sedan', 'count_washes' => 4]);
    $price = PackagePrice::query()->firstOrCreate(['package_id' => $package->id, 'month' => 1], ['price' => 40]);
    $bought = UserPackage::query()->create(['user_id' => $driver->id, 'user_car_id' => $car->id, 'package_id' => $package->id,
        'price_id' => $price->id, 'start_date' => now()->subDay(), 'end_date' => now()->addMonth(),
        'number_of_washes' => 4, 'used_washes' => 0, 'qr_code' => Str::random(16)]);

    return compact('driver', 'car', 'package', 'price', 'bought');
}

function frFlitt(): MockInterface
{
    $flitt = Mockery::mock(FlittCheckout::class)->makePartial();
    $flitt->shouldReceive('checkoutUrl')->andReturnUsing(fn (TbcPayment $p) => 'https://pay.example/'.$p->merchant_payment_id);
    app()->instance(FlittCheckout::class, $flitt);

    return $flitt;
}

// 1. A company can't take a driver's own car without their yes

it('asks the driver before a company takes the car they already have, and keeps it theirs on a no', function () {
    $company = frCompany();
    $f = frDriver('555 75 00 01', 'FR100FR');
    $fleet = frFleetCar($company, 'FR100FR', '555750001');

    // Signing in links nothing: the app shows the question.
    $invites = $this->withToken(cwAs($f['driver']))->getJson('/api/me')->assertOk()->json('fleet_invites');
    expect($fleet->fresh()->only(['status', 'user_car_id', 'activation_error']))
        ->toBe(['status' => 'invited', 'user_car_id' => null, 'activation_error' => 'driver_consent'])
        ->and($invites)->toHaveCount(1)
        ->and($invites[0])->toMatchArray(['id' => $fleet->id, 'company' => 'Taxi Co', 'year' => 2020])
        ->and(FleetInvites::isCompanyCar($f['car']->id))->toBeFalse();
    $this->withToken(cwAs($f['driver']))->getJson('/api/fleet-invites')->assertOk()->assertJsonCount(1, 'invites');

    // Another user can't answer it.
    $other = makeUser('555 75 00 09');
    $this->withToken(cwAs($other))->postJson('/api/fleet-invites/'.$fleet->id.'/accept')->assertNotFound();

    // No: the car stays the driver's, signing in again doesn't link it, the company sees the answer.
    $this->withToken(cwAs($f['driver']))->postJson('/api/fleet-invites/'.$fleet->id.'/decline')->assertOk();
    $this->withToken(cwAs($f['driver']))->getJson('/api/me')->assertOk()->assertJsonCount(0, 'fleet_invites');
    expect($fleet->fresh()->only(['status', 'user_car_id', 'activation_error']))
        ->toBe(['status' => 'invited', 'user_car_id' => null, 'activation_error' => 'driver_declined']);
    $this->withToken(cwAs($f['driver']))->deleteJson('/api/mycars/'.$f['car']->id.'/remove')->assertStatus(422)
        ->assertJsonPath('error_code', 'car_has_package');

    // The company invites again: the driver is asked again, and a yes links the car with its packages.
    $fleet->forceFill(['invited_at' => null])->save();
    expect(FleetInvites::invite($fleet->fresh()))->toBe('sent');
    $this->withToken(cwAs($f['driver']))->getJson('/api/me')->assertOk()->assertJsonCount(1, 'fleet_invites');
    $this->withToken(cwAs($f['driver']))->postJson('/api/fleet-invites/'.$fleet->id.'/accept')->assertOk();
    expect($fleet->fresh()->only(['status', 'user_car_id', 'activation_error']))
        ->toBe(['status' => 'active', 'user_car_id' => $f['car']->id, 'activation_error' => null])
        ->and($fleet->fresh()->driver_consented_at)->not->toBeNull();
    $this->withToken(cwAs($f['driver']))->postJson('/api/fleet-invites/'.$fleet->id.'/accept')->assertNotFound();
});

it('links a car the driver did not have without asking, and a re-saved driver without asking again', function () {
    frModel();
    $company = frCompany();
    $driver = makeUser('555 75 00 02');
    $fleet = frFleetCar($company, 'FR200FR', '555750002');

    // Nothing of the driver's is at stake: the car is made for them at once.
    $this->withToken(cwAs($driver))->getJson('/api/me')->assertOk()->assertJsonCount(0, 'fleet_invites');
    $fleet->refresh();
    expect($fleet->status)->toBe(FleetCar::STATUS_ACTIVE)->and($fleet->user_car_id)->not->toBeNull();

    // The company saves the same driver again: their car comes back to this company car without a question.
    expect(FleetInvites::setDriver($fleet, '555 75 00 02'))->toBeIn(['sent', 'too_soon']);
    $this->withToken(cwAs($driver))->getJson('/api/me')->assertOk()->assertJsonCount(0, 'fleet_invites');
    expect($fleet->fresh()->status)->toBe(FleetCar::STATUS_ACTIVE);
});

it('asks a new driver again: a yes was the old phone\'s', function () {
    $company = frCompany();
    $f = frDriver('555 75 00 03', 'FR300FR');
    $fleet = frFleetCar($company, 'FR300FR', '555750003');
    $this->withToken(cwAs($f['driver']))->getJson('/api/me')->assertOk();
    $this->withToken(cwAs($f['driver']))->postJson('/api/fleet-invites/'.$fleet->id.'/accept')->assertOk();

    FleetInvites::setDriver($fleet->fresh(), '555 75 00 04');

    expect($fleet->fresh()->driver_consented_at)->toBeNull();
});

// 3. A closed promo page is asked about before a new one

it('counts the code as used when the closed page turns out paid, instead of giving the discount twice', function () {
    $flitt = frFlitt();
    AppSettings::store(['point_price' => 1, 'point_discount' => 0]);
    PromoCode::query()->create(['code' => 'ONCE', 'kind' => 'discount', 'value' => 50]);
    $user = makeUser('555 75 00 05');
    $this->withToken(cwAs($user))->postJson('/api/points/buy', ['amount' => 50, 'promo_code' => 'ONCE'])->assertOk();
    $first = TbcPayment::query()->sole();

    // Paid in its last minute, the answer was late: a new page asks Flitt first.
    $this->travel(TbcPayment::CHECKOUT_MINUTES + 1)->minutes();
    $flitt->shouldReceive('status')->with($first->merchant_payment_id)->once()
        ->andReturn(['order_id' => $first->merchant_payment_id, 'order_status' => 'approved', 'amount' => $flitt->minor($first)]);
    $this->withToken(cwAs($user))->postJson('/api/points/buy', ['amount' => 50, 'promo_code' => 'ONCE'])->assertStatus(422)
        ->assertJsonPath('code', 'promo_already_used');

    expect($first->fresh()->status)->toBe('succeeded')->and((int) $user->fresh()->points)->toBe(50)
        ->and(TbcPayment::query()->count())->toBe(1);
});

// 4. A package paid late follows its car

it('gives a package paid after the driver change to the car\'s new driver', function () {
    $company = frCompany();
    $f = frDriver('555 75 00 06', 'FR600FR');
    $fleet = frFleetCar($company, 'FR600FR', '555750006');
    $this->withToken(cwAs($f['driver']))->getJson('/api/me')->assertOk();
    $this->withToken(cwAs($f['driver']))->postJson('/api/fleet-invites/'.$fleet->id.'/accept')->assertOk();

    // The old driver opened a package page; meanwhile the company gave the car to a new driver, who signed in.
    $payment = TbcPayment::query()->create(['merchant_payment_id' => 'PFRLATE', 'user_id' => $f['driver']->id, 'type' => 'package',
        'amount' => 40, 'currency' => 'GEL', 'status' => 'pending',
        'payload' => ['package_id' => $f['package']->id, 'price_id' => $f['price']->id, 'car_id' => $f['car']->id, 'month' => 1]]);
    FleetInvites::setDriver($fleet->fresh(), '555 75 00 07');
    $next = makeUser('555 75 00 07');
    $this->withToken(cwAs($next))->getJson('/api/me')->assertOk();
    expect((int) $f['car']->fresh()->user_id)->toBe((int) $next->id);

    app(SettleTbcPayment::class)->grantNow($payment);

    $late = UserPackage::query()->where('tbc_payment_id', $payment->id)->sole();
    expect((int) $late->user_id)->toBe((int) $next->id)->and($late->rectoken)->toBeNull()
        ->and((int) $late->user_car_id)->toBe((int) $f['car']->id);
    $this->withToken(cwAs($f['driver']))->get('/api/packages/'.$late->id.'/qr')->assertNotFound();
});

it('keeps a package paid while the car waits for its next driver on the car, so the handover brings it', function () {
    $company = frCompany();
    $f = frDriver('555 75 00 08', 'FR800FR');
    $fleet = frFleetCar($company, 'FR800FR', '555750008');
    $this->withToken(cwAs($f['driver']))->getJson('/api/me')->assertOk();
    $this->withToken(cwAs($f['driver']))->postJson('/api/fleet-invites/'.$fleet->id.'/accept')->assertOk();
    $payment = TbcPayment::query()->create(['merchant_payment_id' => 'PFRWAIT', 'user_id' => $f['driver']->id, 'type' => 'package',
        'amount' => 40, 'currency' => 'GEL', 'status' => 'pending',
        'payload' => ['package_id' => $f['package']->id, 'price_id' => $f['price']->id, 'car_id' => $f['car']->id, 'month' => 1]]);
    FleetInvites::setDriver($fleet->fresh(), '555 75 00 10');

    app(SettleTbcPayment::class)->grantNow($payment);
    $next = makeUser('555 75 00 10');
    $this->withToken(cwAs($next))->getJson('/api/me')->assertOk();

    expect((int) UserPackage::query()->where('tbc_payment_id', $payment->id)->value('user_id'))->toBe((int) $next->id);
});

// 6. Deleting the account voids the codes already shown

it('voids package and company codes already on screen when the account is deleted', function () {
    $company = frCompany();
    $f = frDriver('555 75 00 11', 'FR110FR');
    $this->withToken(cwAs($f['driver']))->get('/api/packages/'.$f['bought']->id.'/qr')->assertOk();
    $fleet = frFleetCar($company, 'FR111FR', '555750011');
    $this->withToken(cwAs($f['driver']))->getJson('/api/me')->assertOk();
    $companyCar = (int) $fleet->fresh()->user_car_id;
    $this->withToken(cwAs($f['driver']))->get('/api/mycars/'.$companyCar.'/company-qr')->assertOk();
    expect($f['bought']->fresh()->qr_expires_at)->not->toBeNull()->and($fleet->fresh()->qr_code)->not->toBeNull();

    $this->withToken(cwAs($f['driver']))->postJson('/api/account/delete')->assertOk();

    expect($f['bought']->fresh()->qr_expires_at)->toBeNull()
        ->and($fleet->fresh()->only(['qr_code', 'qr_expires_at']))->toBe(['qr_code' => null, 'qr_expires_at' => null]);
});

// 7. Erasing clears the VIN of cars removed before

it('clears the VIN of cars the user removed before when the account is erased', function () {
    $f = frDriver('555 75 00 12', 'FR120FR');
    $removed = UserCar::query()->create(['user_id' => $f['driver']->id, 'model_id' => frModel()->id, 'plate' => 'FR121FR', 'vin' => 'JTOLDCAR000000001']);
    $removed->delete();

    $this->withToken(cwAs($f['driver']))->postJson('/api/account/delete')->assertOk();
    $this->travel(91)->days();
    $this->artisan('accounts:erase-deleted')->assertSuccessful();

    expect(UserCar::withTrashed()->whereKey([$removed->id, $f['car']->id])->pluck('vin')->filter()->all())->toBe([]);
});

// 8. The open QR screen checks the code it shows

it('tells the app the code it shows is gone once another phone took a newer one', function () {
    $f = frDriver('555 75 00 13', 'FR130FR');
    $shown = $this->withToken(cwAs($f['driver']))->get('/api/packages/'.$f['bought']->id.'/qr')->assertOk()
        ->assertHeader('X-QR-Id')->headers->get('X-QR-Id');
    expect($shown)->toBe(QrCodeId::of((string) $f['bought']->fresh()->qr_code));

    $check = fn (?string $id = null) => $this->withToken(cwAs($f['driver']))
        ->getJson('/api/packages/'.$f['bought']->id.'/qr/live'.($id === null ? '' : '?code='.$id))->assertOk()->json();
    expect($check($shown))->toMatchArray(['live' => true, 'replaced' => false]);

    // Another phone of the same driver takes a newer code: this one is gone, even though a code is live.
    $this->withToken(cwAs($f['driver']))->get('/api/packages/'.$f['bought']->id.'/qr')->assertOk();
    expect($check($shown))->toMatchArray(['live' => false, 'replaced' => true])
        ->and($check()['live'])->toBeTrue();

    // A wash spends the code: gone, and not because of another phone.
    $f['bought']->forceFill(['qr_expires_at' => null])->save();
    expect($check($shown))->toMatchArray(['live' => false, 'replaced' => false]);
});

it('checks the company code the app shows the same way', function () {
    $company = frCompany();
    $driver = makeUser('555 75 00 14');
    frModel();
    $fleet = frFleetCar($company, 'FR140FR', '555750014');
    $this->withToken(cwAs($driver))->getJson('/api/me')->assertOk();
    $carId = (int) $fleet->fresh()->user_car_id;

    $shown = $this->withToken(cwAs($driver))->get('/api/mycars/'.$carId.'/company-qr')->assertOk()->headers->get('X-QR-Id');
    $check = fn () => $this->withToken(cwAs($driver))->getJson('/api/mycars/'.$carId.'/company-qr/live?code='.$shown)->assertOk()->json();
    expect($check())->toMatchArray(['live' => true, 'replaced' => false]);

    $this->withToken(cwAs($driver))->get('/api/mycars/'.$carId.'/company-qr')->assertOk();
    expect($check())->toMatchArray(['live' => false, 'replaced' => true]);
});
