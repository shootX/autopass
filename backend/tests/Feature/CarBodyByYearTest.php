<?php

use App\Models\UserCar;

function avensis(array $fields): array
{
    return ['source' => 'carapi', 'brand_id' => 501, 'model_id' => 7] + $fields;
}

it('offers the body types of the model in the year, and asks only when there are several', function () {
    fakeYearCatalog();
    $token = auth('api')->login(makeUser('555 70 70 01'));
    $query = fn (int $year) => '/api/cars/body-types?'.http_build_query(avensis(['year' => $year]));

    $this->withToken($token)->getJson($query(2016))->assertOk()
        ->assertJsonPath('status', 'choose')->assertJsonPath('choose', true)->assertJsonPath('car_type', null)
        ->assertJsonPath('body_types.0.key', 'sedan')->assertJsonPath('body_types.1.key', 'wagon')->assertJsonCount(2, 'body_types');
    $this->withToken($token)->getJson($query(2013))->assertOk()
        ->assertJsonPath('status', 'confirmed')->assertJsonPath('choose', false)->assertJsonPath('car_type', 'sedan');
    $this->withToken($token)->getJson($query(2021))->assertOk()
        ->assertJsonPath('status', 'confirmed')->assertJsonPath('car_type', 'suv');
});

it('confirms one body type on its own, keeps a car with several waiting for the pick, and refuses a type not offered', function () {
    fakeYearCatalog();
    $token = auth('api')->login(makeUser('555 70 70 02'));

    // One type in 2021: confirmed on its own, with the generation's name.
    $this->withToken($token)->postJson('/api/mycars/add', avensis(['year' => 2021, 'plate' => 'AA301AA']))->assertOk()
        ->assertJsonPath('car.car_type', 'suv')->assertJsonPath('car.body_status', 'confirmed')
        ->assertJsonPath('car.generation.id', 73)->assertJsonPath('car.body_type', 'Crossover');

    // A sedan and a wagon in 2016: the car is saved, waiting for the driver's pick, with no type and no generation.
    $this->withToken($token)->postJson('/api/mycars/add', avensis(['year' => 2016, 'plate' => 'AA302AA']))->assertOk()
        ->assertJsonPath('car.body_status', 'choose')->assertJsonPath('car.car_type', null)->assertJsonPath('car.generation', null)
        ->assertJsonPath('car.body_types.0.key', 'sedan')->assertJsonPath('car.body_types.1.key', 'wagon');

    // A type that is not one of geoapi.ge's is refused and nothing is saved.
    $this->withToken($token)->postJson('/api/mycars/add', avensis(['year' => 2016, 'plate' => 'AA303AA', 'car_type' => 'suv']))->assertStatus(422)
        ->assertJsonPath('error_code', 'body_type_invalid')->assertJsonCount(2, 'body_types');
    expect(UserCar::query()->where('plate', 'AA303AA')->exists())->toBeFalse();

    $this->withToken($token)->postJson('/api/mycars/add', avensis(['year' => 2016, 'plate' => 'AA303AA', 'car_type' => 'wagon']))->assertOk()
        ->assertJsonPath('car.car_type', 'wagon')->assertJsonPath('car.body_status', 'confirmed')->assertJsonPath('car.generation.id', 72);
    expect(UserCar::query()->where('plate', 'AA303AA')->value('body_source'))->toBe('driver');
});

it('reads the body type again when the year changes', function () {
    fakeYearCatalog();
    $token = auth('api')->login(makeUser('555 70 70 03'));
    $id = $this->withToken($token)->postJson('/api/mycars/add', avensis(['year' => 2016, 'plate' => 'AA401AA', 'car_type' => 'wagon']))
        ->assertOk()->json('car.id');

    // 2017 still has a wagon: the pick stays.
    $this->withToken($token)->postJson("/api/mycars/{$id}/edit", ['year' => 2017])->assertOk();
    expect(UserCar::query()->find($id)->only(['year', 'car_type']))->toBe(['year' => 2017, 'car_type' => 'wagon']);

    // 2021 has only an SUV.
    $this->withToken($token)->postJson("/api/mycars/{$id}/edit", ['year' => 2021])->assertOk();
    expect(UserCar::query()->find($id)->only(['year', 'car_type', 'generation_id']))->toBe(['year' => 2021, 'car_type' => 'suv', 'generation_id' => 73]);

    // Back to 2016 with a sedan picked.
    $this->withToken($token)->postJson("/api/mycars/{$id}/edit", ['year' => 2016, 'car_type' => 'sedan'])->assertOk();
    expect(UserCar::query()->find($id)->car_type)->toBe('sedan');
});

it('guesses nothing when geoapi.ge names no body type it knows', function () {
    fakeYearCatalog([['id' => '71', 'name' => 'T27', 'year_start' => 2012, 'year_end' => null, 'body_type' => 'caravan']]);
    $token = auth('api')->login(makeUser('555 70 70 04'));

    $this->withToken($token)->getJson('/api/cars/body-types?'.http_build_query(avensis(['year' => 2016])))->assertOk()
        ->assertJsonPath('status', 'unknown')->assertJsonPath('choose', false)->assertJsonCount(0, 'body_types')
        ->assertJsonPath('unavailable', false);
    // A pick can't be checked against anything, so it is not taken; nor is the model's type.
    $this->withToken($token)->postJson('/api/mycars/add', avensis(['year' => 2016, 'plate' => 'AA501AA', 'car_type' => 'van']))->assertOk()
        ->assertJsonPath('car.car_type', null)->assertJsonPath('car.body_status', 'unknown');
    expect(UserCar::query()->where('plate', 'AA501AA')->value('car_type'))->toBeNull();
});
