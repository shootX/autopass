<?php

use App\Models\BodyType;
use App\Models\CorporateClient;
use App\Models\CorporatePackage;
use App\Models\FleetCar;
use App\Models\UserCar;
use App\Services\CorporateDiscounts;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

function bodyCorp(): array
{
    $plain = CorporateClient::makeToken();
    $corp = CorporateClient::forceCreate([
        'name' => 'Body Co', 'api_token' => $plain, 'username' => 'bodyco', 'password' => Hash::make('corppass1'),
        'password_must_change' => false, 'session_version' => 1,
    ]);

    return [$corp, ['X-Api-Key' => $plain]];
}

it('requires the body type over the API but waits for the driver on ambiguous geoapi.ge\'s types', function () {
    fakeYearCatalog();
    [$corp, $key] = bodyCorp();

    $this->postJson('/api/corporate/vehicles', ['vehicles' => [
        ['plate' => 'AA111AA', 'brand' => 'Toyota', 'model' => 'Avensis', 'year' => 2021, 'body_type' => 'suv'],
        ['plate' => 'AA222AA', 'brand' => 'Toyota', 'model' => 'Avensis', 'year' => 2016],
        ['plate' => 'AA333AA', 'brand' => 'Toyota', 'model' => 'Avensis', 'year' => 2016, 'body_type' => 'უნივერსალი'],
        ['plate' => 'AA444AA', 'brand' => 'Toyota', 'model' => 'Avensis', 'year' => 2016, 'body_type' => 'suv'],
        ['plate' => 'AA445AA', 'brand' => 'Toyota', 'model' => 'Avensis', 'year' => 2016, 'body_type' => 'boat'],
        ['plate' => 'AA555AA', 'brand' => 'Toyota', 'model' => 'Avensis', 'body_type' => 'sedan'],
        ['plate' => 'AA666AA', 'brand' => 'Toyota', 'model' => 'Avensis', 'year' => 2005, 'body_type' => 'sedan'],
    ]], $key)->assertOk()
        ->assertJsonPath('added', 2)
        ->assertJsonPath('pending.0', ['plate' => 'AA333AA', 'body_status' => 'choose', 'body_types' => ['sedan', 'wagon']])
        ->assertJsonPath('skipped.0', ['plate' => 'AA222AA', 'reason' => 'body_type_required'])
        // geoapi.ge names every body the 2016 Avensis had (sedan, wagon): an SUV is not one of them.
        ->assertJsonPath('skipped.1', ['plate' => 'AA444AA', 'reason' => 'body_type_mismatch', 'body_types' => ['sedan', 'wagon']])
        ->assertJsonPath('skipped.2.reason', 'body_type_invalid')
        ->assertJsonCount(16, 'skipped.2.body_types')
        ->assertJsonPath('skipped.3.reason', 'year_invalid')
        ->assertJsonPath('skipped.4.reason', 'year_invalid');

    $cars = FleetCar::query()->orderBy('plate')->get()->keyBy('plate');
    expect($cars['AA111AA']->only(['year', 'car_type', 'body_status', 'body_source', 'catalog_source', 'catalog_model_id', 'generation_id']))
        ->toBe(['year' => 2021, 'car_type' => 'suv', 'body_status' => 'confirmed', 'body_source' => 'catalog', 'catalog_source' => 'carapi', 'catalog_model_id' => 7, 'generation_id' => 73])
        ->and($cars['AA333AA']->only(['car_type', 'body_status', 'body_source', 'generation_id']))
        ->toBe(['car_type' => null, 'body_status' => 'choose', 'body_source' => null, 'generation_id' => null])
        ->and($cars->has('AA444AA'))->toBeFalse();

    $this->getJson('/api/corporate/vehicles', $key)->assertOk()
        ->assertJsonPath('vehicles.1.plate', 'AA333AA')->assertJsonPath('vehicles.1.body_status', 'choose')
        ->assertJsonPath('vehicles.1.body_type', null);
});

it('requires the body type column of a sheet and lists the rows without one', function () {
    fakeYearCatalog();
    [$corp] = bodyCorp();
    $csv = "ნომერი,მარკა,მოდელი,წელი,ძარა,ტელეფონი\nAA111AA,Toyota,Avensis,2016,Station wagon,\nAA222AA,Toyota,Avensis,2016,,\nAA223AA,Toyota,Avensis,2016,boat,\n";

    $this->withSession(['corporate_client_id' => $corp->id, 'corporate_session_version' => 1])
        ->post('/partner/import', ['file' => UploadedFile::fake()->createWithContent('fleet.csv', $csv)])
        ->assertSessionHas('fleet_import_errors', [
            ['row' => 3, 'plate' => 'AA222AA', 'reason' => 'body_type_required'],
            ['row' => 4, 'plate' => 'AA223AA', 'reason' => 'body_type_invalid'],
        ]);
    expect(FleetCar::query()->where('plate', 'AA111AA')->first()->only(['car_type', 'body_status']))->toBe(['car_type' => null, 'body_status' => 'choose'])
        ->and(FleetCar::query()->count())->toBe(1);
    $this->get('/partner/fleet')->assertOk()->assertSee(__('admin.fleet_reason_body_type_required'));
});

it('fills the panel form from the catalog and always asks the body type', function () {
    fakeYearCatalog();
    [$corp] = bodyCorp();
    $panel = $this->withSession(['corporate_client_id' => $corp->id, 'corporate_session_version' => 1]);

    $panel->get('/partner/fleet')->assertOk()->assertSee('name="body_type" required', false)
        ->assertSee(BodyType::labelFor('tractor_unit'))->assertSee('Toyota');
    $panel->getJson('/partner/catalog/brands/501/models')->assertOk()->assertJsonPath('models.0', ['id' => 7, 'name' => 'Avensis', 'source' => 'carapi']);
    $pick = ['source' => 'carapi', 'brand_id' => 501, 'model_id' => 7];
    expect($panel->getJson('/partner/catalog/years?'.http_build_query($pick))->assertOk()->json('years'))->toContain(2016)->not->toContain(2005);
    $panel->getJson('/partner/catalog/body-types?'.http_build_query($pick + ['year' => 2016]))->assertOk()
        ->assertJsonPath('status', 'choose')->assertJsonCount(2, 'body_types');
    $panel->getJson('/partner/catalog/body-types?'.http_build_query($pick + ['year' => 2021]))->assertOk()
        ->assertJsonPath('status', 'confirmed')->assertJsonPath('body_types.0.key', 'suv');

    $form = ['phone' => '555 12 34 56', 'brand_id' => 501, 'model_id' => 7, 'year' => 2016];
    $panel->post('/partner/cars', $form + ['plate' => 'AA776AA'])->assertSessionHasErrors('body_type');
    $panel->post('/partner/cars', $form + ['plate' => 'AA776AA', 'body_type' => 'boat'])->assertSessionHasErrors('body_type');
    $panel->post('/partner/cars', $form + ['plate' => 'AA777AA', 'body_type' => 'sedan'])->assertSessionHasNoErrors()
        ->assertSessionHas('message', __('admin.fleet_car_added_pending'));
    expect(FleetCar::query()->where('plate', 'AA777AA')->first()->only(['car_type', 'body_status', 'body_source']))
        ->toBe(['car_type' => null, 'body_status' => 'choose', 'body_source' => null])
        ->and(FleetCar::query()->where('plate', 'AA776AA')->exists())->toBeFalse();

    // The catalog endpoints are the company's own: no session, no catalog.
    $this->flushSession();
    $this->get('/partner/catalog/brands/501/models')->assertRedirect('/partner/login');
});

it('prices an ambiguous company car only after the driver confirms its body', function () {
    fakeYearCatalog();
    [$corp, $key] = bodyCorp();
    $package = CorporatePackage::query()->forceCreate(['corporate_client_id' => $corp->id, 'title' => 'Disc', 'type' => CorporatePackage::TYPE_DISCOUNT, 'washes' => 0, 'is_active' => true]);
    $package->prices()->createMany([
        ['car_type' => 'sedan', 'full_price' => 30, 'percent' => 50],
        ['car_type' => 'wagon', 'full_price' => 40, 'percent' => 50],
    ]);
    $this->postJson('/api/corporate/vehicles', ['plate' => 'AA888AA', 'brand' => 'Toyota', 'model' => 'Avensis', 'year' => 2016,
        'body_type' => 'wagon', 'phone' => '555 12 34 56'], $key)->assertOk()->assertJsonPath('added', 1);
    $car = FleetCar::query()->where('plate', 'AA888AA')->firstOrFail();
    $car->forceFill(['mode' => FleetCar::MODE_DISCOUNT, 'corporate_package_id' => $package->id])->save();

    // Company input alone is not confirmation.
    expect(CorporateDiscounts::forCar($car->fresh()))->toBeNull();

    $driver = makeUser('555 12 34 56');
    $driver->forceFill(['password' => Hash::make('driverpass')])->save();
    $token = $this->postJson('/api/login', ['phone' => '555123456', 'password' => 'driverpass'])->assertOk()->json('access_token');
    $userCar = UserCar::query()->where('plate', 'AA888AA')->firstOrFail();
    $this->withToken($token)->postJson('/api/mycars/'.$userCar->id.'/body', ['car_type' => 'wagon'])->assertOk();
    $userCar->refresh();
    expect((float) CorporateDiscounts::forCar($car->fresh())->full_price)->toBe(40.0);
    expect((int) $userCar->user_id)->toBe((int) $driver->id)
        ->and($userCar->only(['year', 'car_type', 'generation_id']))->toBe(['year' => 2016, 'car_type' => 'wagon', 'generation_id' => 72])
        ->and($userCar->model->external_model_id)->toBe(7);
});

it('keeps a company car waiting when geoapi.ge cannot confirm the type, until a re-check or an admin does', function () {
    [$corp, $key] = bodyCorp();
    // geoapi.ge knows the model but its generations don't answer (until $down is cleared).
    Cache::flush();
    config(['services.carapi.url' => 'https://geoapi.test/api/v1', 'services.carapi.secret' => 'geoapi_sk_test']);
    $down = true;
    $generations = [
        ['id' => '71', 'name' => 'T27 Sedan', 'year_start' => 2012, 'year_end' => 2018, 'body_type' => 'Sedan'],
        ['id' => '72', 'name' => 'T27 Wagon', 'year_start' => 2014, 'year_end' => 2018, 'body_type' => 'Station wagon'],
    ];
    Http::fake([
        'geoapi.test/api/v1/models/7/generations*' => function () use (&$down, $generations) {
            return $down
                ? Http::response(['message' => 'down'], 503)
                : Http::response(['data' => $generations, 'meta' => ['total' => count($generations)]]);
        },
        'geoapi.test/api/v1/generations/*' => Http::response(['data' => [], 'meta' => ['total' => 0]]),
        'geoapi.test/api/v1/brands/toyota/models*' => Http::response(['data' => [['id' => '7', 'name' => 'Avensis']], 'meta' => ['total' => 1]]),
        'geoapi.test/api/v1/brands*' => Http::response(['data' => [['id' => '501', 'name' => 'Toyota', 'slug' => 'toyota']], 'meta' => ['total' => 1]]),
    ]);

    $this->postJson('/api/corporate/vehicles', ['vehicles' => [
        ['plate' => 'AB111AB', 'brand' => 'Toyota', 'model' => 'Avensis', 'year' => 2016, 'body_type' => 'wagon'],
    ]], $key)->assertOk()
        ->assertJsonPath('added', 1)
        ->assertJsonPath('pending.0', ['plate' => 'AB111AB', 'body_status' => 'unknown', 'body_types' => []]);

    $car = FleetCar::query()->where('plate', 'AB111AB')->sole();
    expect($car->only(['car_type', 'body_status', 'body_requested']))->toBe(['car_type' => null, 'body_status' => 'unknown', 'body_requested' => 'wagon'])
        ->and($car->carType())->toBeNull()
        ->and($car->bodyConfirmed())->toBeFalse();
    $this->patchJson('/api/corporate/vehicles/AB111AB', ['washes' => 1], $key)->assertStatus(422)
        ->assertJsonPath('body_status', 'unknown');

    // GeoAPI lists several bodies again: re-check still leaves the decision to the driver.
    $down = false;
    Cache::flush();
    $this->patchJson('/api/corporate/vehicles/AB111AB', ['body_check' => true], $key)->assertOk();
    expect($car->fresh()->only(['car_type', 'body_status']))->toBe(['car_type' => null, 'body_status' => 'choose']);
});

it('leaves an incomplete body list for the driver even when the company named a type', function () {
    [$corp, $key] = bodyCorp();
    // 2016: a sedan generation, and one geoapi.ge names no body for, so the list may be missing types.
    fakeYearCatalog([
        ['id' => '71', 'name' => 'T27 Sedan', 'year_start' => 2012, 'year_end' => 2018, 'body_type' => 'Sedan'],
        ['id' => '74', 'name' => 'T27', 'year_start' => 2014, 'year_end' => 2018, 'body_type' => null],
    ]);

    $this->postJson('/api/corporate/vehicles', ['vehicles' => [
        ['plate' => 'AB211AB', 'brand' => 'Toyota', 'model' => 'Avensis', 'year' => 2016, 'body_type' => 'sedan'],
        ['plate' => 'AB222AB', 'brand' => 'Toyota', 'model' => 'Avensis', 'year' => 2016, 'body_type' => 'wagon', 'phone' => '555 12 34 56'],
    ]], $key)->assertOk()
        ->assertJsonPath('added', 2)
        ->assertJsonCount(2, 'pending')
        ->assertJsonPath('pending.1', ['plate' => 'AB222AB', 'body_status' => 'choose', 'body_types' => ['sedan']]);

    $cars = FleetCar::query()->get()->keyBy('plate');
    expect($cars['AB211AB']->only(['car_type', 'body_status']))->toBe(['car_type' => null, 'body_status' => 'choose'])
        ->and($cars['AB222AB']->only(['car_type', 'body_status', 'body_requested']))->toBe(['car_type' => null, 'body_status' => 'choose', 'body_requested' => 'wagon']);

    // A re-check that still can't see a wagon leaves it waiting; so does the driver picking the one type listed.
    $this->patchJson('/api/corporate/vehicles/AB222AB', ['body_check' => true], $key)->assertOk();
    expect($cars['AB222AB']->fresh()->bodyConfirmed())->toBeFalse();
    $driver = makeUser('555 12 34 56');
    $driver->forceFill(['password' => Hash::make('driverpass')])->save();
    $token = $this->postJson('/api/login', ['phone' => '555123456', 'password' => 'driverpass'])->assertOk()->json('access_token');
    $userCar = UserCar::query()->where('plate', 'AB222AB')->sole();
    $this->withToken($token)->postJson('/api/mycars/'.$userCar->id.'/body', ['car_type' => 'sedan'])->assertOk();
    $this->flushHeaders();
    expect($userCar->fresh()->car_type)->toBe('sedan')
        ->and($cars['AB222AB']->fresh()->only(['car_type', 'body_status']))->toBe(['car_type' => 'sedan', 'body_status' => 'confirmed']);

    // The admin confirms it by hand.
    $this->actingAs(cwAdmin());
    $this->get(route('admin.corporate.show', $corp))->assertOk()->assertSee(__('admin.fleet_body_status_waiting'));
    $this->post(route('admin.corporate.cars.body', [$corp, $cars['AB222AB']]), ['body_type' => 'wagon'])->assertSessionHasNoErrors();
    expect($cars['AB222AB']->fresh()->only(['car_type', 'body_status', 'body_source']))
        ->toBe(['car_type' => 'wagon', 'body_status' => 'confirmed', 'body_source' => 'admin']);
});
