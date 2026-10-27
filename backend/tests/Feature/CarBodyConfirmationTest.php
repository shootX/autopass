<?php

use App\Models\Package;
use App\Models\PackagePrice;
use App\Models\User;
use App\Models\UserCar;
use App\Models\UserPackage;
use App\Services\CarBodyStatus;
use App\Services\CarCatalog;
use App\Services\Payments\FlittCheckout;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/** A driver with a 2016 Avensis waiting for a pick (sedan or wagon) and a 2021 one confirmed as an SUV. */
function bodyDriver(string $phone): array
{
    fakeYearCatalog();
    foreach (['sedan', 'wagon', 'suv'] as $type) {
        $package = Package::query()->create(['car_type' => $type, 'count_washes' => 4]);
        PackagePrice::query()->create(['package_id' => $package->id, 'month' => 1, 'price' => 40]);
    }
    $checkout = Mockery::mock(FlittCheckout::class);
    $checkout->shouldReceive('checkoutUrl')->andReturn('https://checkout.example.test/body');
    app()->instance(FlittCheckout::class, $checkout);

    $user = makeUser($phone);
    $token = auth('api')->login($user);
    $pending = waitingCar($user, 2016, 'BD101BD');
    $suv = test()->withToken($token)->postJson('/api/mycars/add',
        ['source' => 'carapi', 'brand_id' => 501, 'model_id' => 7, 'year' => 2021, 'generation_id' => 73, 'plate' => 'BD102BD'])->assertOk()->json('car.id');

    return ['user' => $user, 'token' => $token, 'pending' => $pending->fresh(), 'suv' => UserCar::query()->find($suv)];
}

/**
 * A geoapi.ge Avensis saved before generations were required: no generation, so its year's types decide (an
 * app can no longer add one like this).
 */
function waitingCar(User $user, int $year, string $plate): UserCar
{
    $modelId = app(CarCatalog::class)->resolveModelId(CarCatalog::REMOTE, 7, 501);
    $car = UserCar::query()->create(['user_id' => $user->id, 'model_id' => $modelId, 'plate' => $plate, 'year' => $year]);
    app(CarBodyStatus::class)->recheck($car, 'test');

    return $car->fresh();
}

function bodyPackage(UserCar $car, bool $legacy = false): UserPackage
{
    $package = UserPackage::query()->create([
        'user_id' => $car->user_id, 'user_car_id' => $car->id, 'package_id' => Package::query()->value('id'),
        'start_date' => now()->subDay(), 'end_date' => now()->addMonth(), 'number_of_washes' => 4, 'used_washes' => 0,
        'qr_code' => Str::random(16),
    ]);
    $package->forceFill(['body_legacy' => $legacy])->save();

    return $package;
}

it('lets the driver pick only one of the offered types, confirms it, and gives nothing on its own', function () {
    $f = bodyDriver('555 71 00 01');
    $car = $f['pending'];

    $this->withToken($f['token'])->getJson('/api/mycars')->assertOk()
        ->assertJsonPath('cars.0.body_status', 'choose')->assertJsonPath('cars.0.body_types.1.key', 'wagon');

    $this->withToken($f['token'])->postJson("/api/mycars/{$car->id}/body", ['car_type' => 'suv'])->assertStatus(422)
        ->assertJsonPath('error_code', 'body_type_invalid')->assertJsonCount(2, 'body_types');
    expect($car->fresh()->body_status)->toBe('choose');

    $this->withToken($f['token'])->postJson("/api/mycars/{$car->id}/body", ['car_type' => 'wagon'])->assertOk()
        ->assertJsonPath('car.car_type', 'wagon')->assertJsonPath('car.body_status', 'confirmed')->assertJsonPath('car.generation.id', 72);
    expect($car->fresh()->only(['body_source', 'car_type']))->toBe(['body_source' => 'driver', 'car_type' => 'wagon'])
        // Confirming gives no package and no wash.
        ->and(UserPackage::query()->count())->toBe(0);

    // Once confirmed, the driver can't switch it.
    $this->withToken($f['token'])->postJson("/api/mycars/{$car->id}/body", ['car_type' => 'sedan'])->assertStatus(422)
        ->assertJsonPath('error_code', 'body_confirmed');
    // Another driver's car is not found.
    $this->withToken(auth('api')->login(makeUser('555 71 00 09')))->postJson("/api/mycars/{$car->id}/body", ['car_type' => 'sedan'])->assertNotFound();
});

it('offers and sells no package for a car until its body type is confirmed, while the other car works', function () {
    $f = bodyDriver('555 71 00 02');
    $buy = fn (UserCar $car) => ['car_id' => $car->id, 'number_of_washes' => 4, 'renewal' => false, 'sub_term' => 1];

    $this->withToken($f['token'])->getJson('/api/packages?carid='.$f['pending']->id)->assertStatus(422)->assertJsonPath('error_code', 'body_unconfirmed');
    $this->withToken($f['token'])->postJson('/api/packages/buy', $buy($f['pending']))->assertStatus(422)->assertJsonPath('error_code', 'body_unconfirmed');

    $this->withToken($f['token'])->getJson('/api/packages?carid='.$f['suv']->id)->assertOk()->assertJsonPath('packages.0.car_type', 'suv');
    $this->withToken($f['token'])->postJson('/api/packages/buy', $buy($f['suv']))->assertOk()->assertJsonPath('success', true);

    // Confirmed: the package of its type is offered.
    $this->withToken($f['token'])->postJson("/api/mycars/{$f['pending']->id}/body", ['car_type' => 'sedan'])->assertOk();
    $this->withToken($f['token'])->getJson('/api/packages?carid='.$f['pending']->id)->assertOk()
        ->assertJsonPath('packages.0.car_type', 'sedan')->assertJsonCount(1, 'packages');
});

it('lets the admin give a package only to a car of a confirmed body type', function () {
    $f = bodyDriver('555 71 00 12');
    $price = fn (string $type) => PackagePrice::query()->whereHas('package', fn ($q) => $q->where('car_type', $type))->value('id');
    $store = '/dashboard/clients/'.$f['user']->id.'/packages/store';

    $this->actingAs(cwAdmin())->post($store, ['car_id' => $f['pending']->id, 'price_id' => $price('sedan')])->assertSessionHasErrors('car_id');
    $this->actingAs(cwAdmin())->post($store, ['car_id' => $f['suv']->id, 'price_id' => $price('sedan')])->assertSessionHasErrors('car_id');
    expect(UserPackage::query()->count())->toBe(0);
    $this->actingAs(cwAdmin())->post($store, ['car_id' => $f['suv']->id, 'price_id' => $price('suv')])->assertSessionHasNoErrors();
    expect(UserPackage::query()->where('user_car_id', $f['suv']->id)->count())->toBe(1);

    // The admin may confirm a type by hand, for a car geoapi.ge can't tell.
    $this->actingAs(cwAdmin())->post('/dashboard/clients/'.$f['user']->id.'/cars/'.$f['pending']->id.'/body', ['body_type' => 'wagon'])
        ->assertSessionHasNoErrors();
    expect($f['pending']->fresh()->only(['car_type', 'body_status', 'body_source']))->toBe(['car_type' => 'wagon', 'body_status' => 'confirmed', 'body_source' => 'admin']);
    $this->actingAs(cwAdmin())->get('/dashboard/clients/'.$f['user']->id.'/cars')->assertOk();
});

it('shows no QR and approves no wash for an unconfirmed car, but keeps a package bought before the change working', function () {
    $f = bodyDriver('555 71 00 03');
    $manager = makeUser('555 71 00 08', User::ROLE_MANAGER);
    cwManager('555 71 00 07');
    DB::table('car_washes')->insert(['name' => 'B', 'address' => 'a', 'work_time_start' => '09:00',
        'work_time_end' => '20:00', 'location' => 'x', 'manager_id' => $manager->id, 'created_at' => now(), 'updated_at' => now()]);
    $new = bodyPackage($f['pending']);

    $this->withToken($f['token'])->get("/api/packages/{$new->id}/qr")->assertStatus(422)->assertJsonPath('error_code', 'body_unconfirmed');
    $this->withToken(cwAs($manager))->getJson('/api/checkqr/'.$new->qr_code)->assertStatus(422)->assertJsonPath('error_code', 'body_unconfirmed');
    $this->withToken(cwAs($manager))->postJson("/api/package/{$new->id}/approve", ['approve_token' => approveTokenFor($manager, $new->id)])
        ->assertStatus(422)->assertJsonPath('error_code', 'body_unconfirmed');
    expect($new->fresh()->used_washes)->toBe(0);

    // A package bought before body types were confirmed still washes.
    $old = bodyPackage($f['pending'], true);
    $this->withToken(cwAs($f['user']))->get("/api/packages/{$old->id}/qr")->assertOk();
    $token = $this->withToken(cwAs($manager))->getJson('/api/checkqr/'.$old->fresh()->qr_code)->assertOk()->json('approve_token');
    $this->withToken(cwAs($manager))->postJson("/api/package/{$old->id}/approve", ['approve_token' => $token])->assertOk();

    // The same driver's confirmed car washes as before.
    $suv = bodyPackage($f['suv']);
    $this->withToken(cwAs($f['user']))->get("/api/packages/{$suv->id}/qr")->assertOk();
});

it('keeps an unknown car unconfirmed while geoapi.ge is down and confirms it on a retry', function () {
    $user = makeUser('555 71 00 04');
    $token = auth('api')->login($user);
    $pick = ['source' => 'carapi', 'brand_id' => 501, 'model_id' => 7, 'year' => 2021];
    config(['services.carapi.url' => 'https://geoapi.test/api/v1', 'services.carapi.secret' => 'k']);
    Http::fake(['*' => Http::response('down', 503)]);

    $this->withToken($token)->getJson('/api/cars/body-types?'.http_build_query($pick))->assertOk()
        ->assertJsonPath('status', 'unknown')->assertJsonPath('unavailable', true)->assertJsonCount(0, 'body_types');
    // A brand list from our own catalog while geoapi.ge is down: the car is kept, but nothing is guessed.
    $car = UserCar::query()->create(['user_id' => $user->id, 'model_id' => cwFleet()['userCar']->model_id, 'plate' => 'BD401BD', 'year' => 2021]);
    $this->withToken($token)->postJson("/api/mycars/{$car->id}/body/check")->assertOk()
        ->assertJsonPath('car.body_status', 'unknown')->assertJsonPath('message', __('cars.body_unavailable'));
    expect($car->fresh()->body_checked_at)->toBeNull();

    // geoapi.ge is back, with a Camry built only as a sedan that year.
    Http::swap(new Factory);
    Cache::flush();
    config(['services.carapi.url' => 'https://geoapi.test/api/v1', 'services.carapi.secret' => 'k']);
    Http::fake([
        'geoapi.test/api/v1/models/8/generations*' => Http::response(['data' => [['id' => '81', 'name' => 'XV70', 'year_start' => 2017, 'year_end' => null, 'body_type' => 'Sedan']], 'meta' => ['total' => 1]]),
        'geoapi.test/api/v1/generations/*' => Http::response(['data' => [], 'meta' => ['total' => 0]]),
        'geoapi.test/api/v1/brands/toyota/models*' => Http::response(['data' => [['id' => '8', 'name' => 'Camry']], 'meta' => ['total' => 1]]),
        'geoapi.test/api/v1/brands*' => Http::response(['data' => [['id' => '501', 'name' => 'Toyota', 'slug' => 'toyota']], 'meta' => ['total' => 1]]),
    ]);
    $this->withToken($token)->postJson("/api/mycars/{$car->id}/body/check")->assertOk()
        ->assertJsonPath('car.body_status', 'confirmed')->assertJsonPath('car.car_type', 'sedan')->assertJsonPath('message', null);
});

it('does not take the one named type as proof when another generation of that year names none', function () {
    fakeYearCatalog([
        ['id' => '71', 'name' => 'T27 Sedan', 'year_start' => 2012, 'year_end' => 2018, 'body_type' => 'Sedan'],
        ['id' => '74', 'name' => 'T27', 'year_start' => 2014, 'year_end' => 2018, 'body_type' => null],
    ]);
    $user = makeUser('555 71 00 05');
    $token = auth('api')->login($user);
    $this->withToken($token)->getJson('/api/cars/body-types?'.http_build_query(['source' => 'carapi', 'brand_id' => 501, 'model_id' => 7, 'year' => 2016]))
        ->assertOk()->assertJsonPath('status', 'choose')->assertJsonPath('incomplete', true)->assertJsonCount(1, 'body_types');

    $id = waitingCar($user, 2016, 'BD501BD')->id;
    $this->withToken($token)->getJson("/api/mycars/{$id}")->assertOk()
        ->assertJsonPath('car.body_status', 'choose')->assertJsonPath('car.body_incomplete', true);
    $this->withToken($token)->postJson("/api/mycars/{$id}/body", ['car_type' => 'sedan'])->assertOk()->assertJsonPath('car.car_type', 'sedan');
});

it('keeps the model and year of a car with a running package', function () {
    $f = bodyDriver('555 71 00 06');
    bodyPackage($f['suv']);

    $this->withToken($f['token'])->postJson("/api/mycars/{$f['suv']->id}/edit", ['year' => 2016])->assertStatus(422)
        ->assertJsonPath('error_code', 'car_has_package');
    $this->withToken($f['token'])->postJson("/api/mycars/{$f['suv']->id}/edit", ['plate' => 'BD109BD'])->assertOk();
    expect($f['suv']->fresh()->only(['year', 'car_type', 'plate']))->toBe(['year' => 2021, 'car_type' => 'suv', 'plate' => 'BD109BD']);
});
