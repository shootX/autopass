<?php

use App\Models\BodyType;
use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\Package;
use App\Models\UserCar;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function fakeCatalog(array $generations): void
{
    Cache::flush();
    config(['services.carapi.url' => 'https://geoapi.test/api/v1', 'services.carapi.secret' => 'geoapi_sk_test']);
    Http::fake([
        'geoapi.test/api/v1/models/9/generations*' => Http::response(['data' => $generations, 'meta' => ['total' => count($generations)]]),
        'geoapi.test/api/v1/brands/toyota/models*' => Http::response(['data' => [['id' => '9', 'name' => 'RAV4']], 'meta' => ['total' => 1]]),
        'geoapi.test/api/v1/brands*' => Http::response(['data' => [['id' => '501', 'name' => 'Toyota', 'slug' => 'toyota']], 'meta' => ['total' => 1]]),
    ]);
}

it('reads our body type from the catalog body name', function (?string $raw, ?string $key) {
    expect(BodyType::fromCatalog($raw))->toBe($key);
})->with([
    ['sedan', 'sedan'],
    ['Sedan 4 doors', 'sedan'],
    ['Hatchback 5-door', 'hatchback'],
    ['liftback', 'liftback'],
    ['Station wagon', 'wagon'],
    ['estate', 'wagon'],
    ['SUV 5 doors', 'suv'],
    ['crossover', 'suv'],
    ['Coupe-SUV', 'suv'],
    ['Off-road vehicle', 'suv'],
    ['coupe', 'coupe'],
    ['cabrio', 'convertible'],
    ['Roadster', 'convertible'],
    ['minivan', 'minivan'],
    ['Compact MPV', 'minivan'],
    ['Pickup truck', 'pickup'],
    ['panel_van', 'panel_van'],
    ['Cargo van', 'panel_van'],
    ['van', 'van'],
    ['Minibus', 'bus'],
    ['Box truck', 'truck'],
    ['Chassis cab', 'chassis_cab'],
    ['Flatbed', 'flatbed_truck'],
    ['Tractor unit', 'tractor_unit'],
    ['Внедорожник', 'suv'],
    ['უნივერსალი', 'wagon'],
    ['Caravan', null],
    ['', null],
    [null, null],
]);

it('gives a new catalog model and a new car the body type the catalog names', function () {
    fakeCatalog([
        ['id' => '91', 'name' => 'XA40', 'year_start' => 2012, 'year_end' => 2018, 'body_type' => 'crossover'],
        ['id' => '92', 'name' => 'XA50', 'year_start' => 2018, 'year_end' => null, 'body_type' => 'SUV 5 doors'],
        ['id' => '93', 'name' => 'XA50 Van', 'year_start' => 2019, 'year_end' => null, 'body_type' => 'cargo van'],
    ]);
    Package::query()->create(['car_type' => 'suv', 'count_washes' => 4]);
    Package::query()->create(['car_type' => 'panel_van', 'count_washes' => 8]);
    $user = makeUser('555 60 60 01');
    $token = auth('api')->login($user);

    $this->withToken($token)->postJson('/api/mycars/add', [
        'source' => 'carapi', 'brand_id' => 501, 'model_id' => 9, 'generation_id' => 93, 'plate' => 'AA101AA',
    ])->assertOk()->assertJsonPath('car.body_type', 'cargo van')->assertJsonPath('car.car_type', 'panel_van');

    // The model takes its generations' most common type; this car keeps its own.
    expect(CarModel::query()->where('external_model_id', 9)->value('type'))->toBe('suv');
    $car = UserCar::query()->where('plate', 'AA101AA')->firstOrFail();
    expect($car->car_type)->toBe('panel_van');
    $this->withToken($token)->getJson('/api/packages?carid='.$car->id)->assertOk()
        ->assertJsonPath('packages.0.car_type', 'panel_van')->assertJsonCount(1, 'packages');

    // Without a generation the car takes the body type its year was built in.
    $this->withToken($token)->postJson('/api/mycars/add', [
        'source' => 'carapi', 'brand_id' => 501, 'model_id' => 9, 'year' => 2015, 'plate' => 'AA102AA',
    ])->assertOk()->assertJsonPath('car.car_type', 'suv')->assertJsonPath('car.generation.id', 91);
    $types = collect($this->withToken($token)->getJson('/api/mycars')->assertOk()->json('cars'))->pluck('car_type', 'plate')->all();
    expect($types)->toBe(['AA101AA' => 'panel_van', 'AA102AA' => 'suv']);
});

it('sets the type of cars and catalog models added before, and reports what it cannot tell', function () {
    fakeCatalog([
        ['id' => '91', 'name' => 'XA40', 'year_start' => 2012, 'year_end' => 2018, 'body_type' => 'crossover'],
    ]);
    Package::query()->create(['car_type' => 'sedan', 'count_washes' => 4]);
    $brand = CarBrand::query()->create(['name' => 'Toyota']);
    $model = CarModel::query()->create(['name' => 'RAV4', 'brand_id' => $brand->id, 'type' => 'sedan']);
    $model->forceFill(['source' => 'carapi', 'external_model_id' => 9])->save();
    $user = makeUser('555 60 60 02');
    $wagon = UserCar::query()->create(['user_id' => $user->id, 'model_id' => $model->id, 'plate' => 'AA201AA', 'body_type' => 'estate']);
    $odd = UserCar::query()->create(['user_id' => $user->id, 'model_id' => $model->id, 'plate' => 'AA202AA', 'body_type' => 'caravan']);

    $this->artisan('cars:sync-body-types --dry-run')->assertSuccessful();
    expect($wagon->fresh()->car_type)->toBeNull()->and($model->fresh()->type)->toBe('sedan');

    $this->artisan('cars:sync-body-types')
        ->expectsOutputToContain('Not recognised: "caravan"')
        ->expectsOutputToContain('No package for "wagon"')
        ->assertSuccessful();
    expect($wagon->fresh()->car_type)->toBe('wagon')
        ->and($odd->fresh()->car_type)->toBeNull()
        ->and($odd->fresh()->carType())->toBe('suv')
        ->and($model->fresh()->type)->toBe('suv');

    $this->actingAs(cwAdmin())->get('/dashboard/body-types')->assertOk()
        ->assertSee('caravan')->assertSee(__('admin.body_type_unpriced'));
});
