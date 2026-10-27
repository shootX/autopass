<?php

use App\Models\UserCar;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * A Ford Focus (geoapi brand 77, model 1243) as geoapi.ge answers since 2026-10-27: the generation's body_type is
 * null and body_types lists every body; each modification names its own body_type.
 */
function fakeFocusCatalog(array $modifications = [
    ['id' => '9001', 'name' => '1.5 EcoBoost Hatchback', 'year_start' => 2018, 'year_end' => null, 'body_type' => 'hatchback', 'drive_type' => 'fwd'],
    ['id' => '9002', 'name' => '1.5 EcoBoost Wagon', 'year_start' => 2018, 'year_end' => null, 'body_type' => 'wagon', 'drive_type' => 'fwd'],
    ['id' => '9003', 'name' => '2.0 TDCi', 'year_start' => 2018, 'year_end' => null, 'body_type' => null],
]): void
{
    Cache::flush();
    config(['services.carapi.url' => 'https://geoapi.test/api/v1', 'services.carapi.secret' => 'geoapi_sk_test']);
    Http::fake([
        'geoapi.test/api/v1/models/1243/generations*' => Http::response(['data' => [
            ['id' => '120', 'name' => 'Restyling', 'year_start' => 2014, 'year_end' => 2019, 'body_type' => null, 'body_types' => ['hatchback', 'sedan', 'wagon'],
                'photo_url' => 'https://geoapi.test/api/v1/models/1243/photo?generation=120'],
            ['id' => '130', 'name' => 'Generation 4', 'year_start' => 2018, 'year_end' => null, 'body_type' => null, 'body_types' => ['hatchback', 'sedan', 'wagon'],
                'photo_url' => 'https://geoapi.test/api/v1/models/1243/photo?generation=130'],
        ], 'meta' => ['total' => 2]]),
        'geoapi.test/api/v1/generations/130/modifications*' => Http::response(['data' => $modifications, 'meta' => ['total' => count($modifications)]]),
        'geoapi.test/api/v1/generations/*' => Http::response(['data' => [], 'meta' => ['total' => 0]]),
        'geoapi.test/api/v1/brands/ford/models*' => Http::response(['data' => [['id' => '1243', 'name' => 'Focus']], 'meta' => ['total' => 1]]),
        'geoapi.test/api/v1/brands*' => Http::response(['data' => [['id' => '77', 'name' => 'Ford', 'slug' => 'ford']], 'meta' => ['total' => 1]]),
    ]);
}

function focus(array $fields): array
{
    return ['source' => 'carapi', 'brand_id' => 77, 'model_id' => 1243, 'year' => 2019, 'fuel_type' => 'premium'] + $fields;
}

it('requires the year, the generation and the modification of a catalog car, each from the list for the year', function () {
    fakeFocusCatalog();
    $token = auth('api')->login(makeUser('555 71 71 01'));
    $add = fn (array $fields) => $this->withToken($token)->postJson('/api/mycars/add', focus(['plate' => 'IO663OI'] + $fields));

    $add(['year' => null])->assertStatus(422)->assertJsonPath('error_code', 'year_required');
    $add([])->assertStatus(422)->assertJsonPath('error_code', 'generation_required');
    $add(['generation_id' => 999])->assertStatus(422)->assertJsonPath('error_code', 'generation_invalid');
    $add(['generation_id' => 130])->assertStatus(422)->assertJsonPath('error_code', 'modification_required');
    $add(['generation_id' => 130, 'modification_id' => 777])->assertStatus(422)->assertJsonPath('error_code', 'modification_invalid');
    expect(UserCar::query()->where('plate', 'IO663OI')->exists())->toBeFalse();
});

it('confirms the body type the modification names, and keeps the generation, modification and photo', function () {
    fakeFocusCatalog();
    $token = auth('api')->login(makeUser('555 71 71 02'));

    $this->withToken($token)->postJson('/api/mycars/add', focus(['plate' => 'IO663OI', 'generation_id' => 130, 'modification_id' => 9002]))->assertOk()
        ->assertJsonPath('car.body_status', 'confirmed')->assertJsonPath('car.car_type', 'wagon')
        ->assertJsonPath('car.generation.id', 130)->assertJsonPath('car.modification.id', 9002)->assertJsonPath('car.body_type', 'wagon');
    $car = UserCar::query()->where('plate', 'IO663OI')->first();
    expect($car->body_source)->toBe('catalog')
        ->and($car->photo)->toBe('/api/cars/models/1243/photo?generation=130');
});

it('offers the generation body types when the modification names none, and keeps the trim after the pick', function () {
    fakeFocusCatalog();
    $token = auth('api')->login(makeUser('555 71 71 03'));

    $id = $this->withToken($token)->postJson('/api/mycars/add', focus(['plate' => 'IO664OI', 'generation_id' => 130, 'modification_id' => 9003]))->assertOk()
        ->assertJsonPath('car.body_status', 'choose')->assertJsonPath('car.car_type', null)
        ->assertJsonPath('car.body_types.0.key', 'sedan')->assertJsonPath('car.body_types.1.key', 'hatchback')->assertJsonPath('car.body_types.2.key', 'wagon')
        ->assertJsonPath('car.modification.id', 9003)->json('car.id');

    $this->withToken($token)->postJson("/api/mycars/{$id}/body", ['car_type' => 'suv'])->assertStatus(422);
    $this->withToken($token)->postJson("/api/mycars/{$id}/body", ['car_type' => 'sedan'])->assertOk()
        ->assertJsonPath('car.car_type', 'sedan')->assertJsonPath('car.body_status', 'confirmed');
    expect(UserCar::query()->find($id)->only(['generation_id', 'modification_id', 'body_source']))
        ->toBe(['generation_id' => 130, 'modification_id' => 9003, 'body_source' => 'driver']);
});

it('does not ask a modification the catalog has none of', function () {
    fakeFocusCatalog([]);
    $token = auth('api')->login(makeUser('555 71 71 04'));

    $this->withToken($token)->postJson('/api/mycars/add', focus(['plate' => 'IO665OI', 'generation_id' => 130]))->assertOk()
        ->assertJsonPath('car.body_status', 'choose')->assertJsonPath('car.generation.id', 130)->assertJsonPath('car.modification', null);
});

it('reads the body_types list of a generation for a car without a picked generation', function () {
    fakeFocusCatalog();
    $token = auth('api')->login(makeUser('555 71 71 05'));

    $this->withToken($token)->getJson('/api/cars/body-types?'.http_build_query(['source' => 'carapi', 'brand_id' => 77, 'model_id' => 1243, 'year' => 2019]))
        ->assertOk()->assertJsonPath('status', 'choose')->assertJsonCount(3, 'body_types');
    $this->withToken($token)->getJson('/api/cars/models/1243/generations?brand_id=77&year=2019')->assertOk()
        ->assertJsonPath('generations.1.body_types', ['hatchback', 'sedan', 'wagon']);
    $this->withToken($token)->getJson('/api/cars/models/1243/generations/130/modifications?year=2019')->assertOk()
        ->assertJsonPath('modifications.0.body_type', 'hatchback')->assertJsonPath('modifications.0.drive', 'fwd');
});
