<?php

use App\Models\UserCar;

/** VIN and colour from the scanned tech passport; the app sends them with the add and edit forms. */
it('saves the VIN and colour of a new car, upper-cased and trimmed', function () {
    fakeFocusCatalog();
    $token = auth('api')->login(makeUser('555 71 72 01'));

    $this->withToken($token)->postJson('/api/mycars/add', focus([
        'plate' => 'KX074XK', 'generation_id' => 130, 'modification_id' => 9001, 'vin' => ' jf2gthrcxmh282237 ', 'color' => '  GRAY  6/8 ',
    ]))->assertOk()->assertJsonPath('car.vin', 'JF2GTHRCXMH282237')->assertJsonPath('car.color', 'GRAY 6/8');

    $car = UserCar::query()->where('plate', 'KX074XK')->first();
    expect($car->vin)->toBe('JF2GTHRCXMH282237')->and($car->color)->toBe('GRAY 6/8');
});

it('refuses a VIN that is not 17 letters and digits, and keeps no car', function () {
    fakeFocusCatalog();
    $token = auth('api')->login(makeUser('555 71 72 02'));
    $add = fn (string $vin) => $this->withToken($token)->postJson('/api/mycars/add', focus([
        'plate' => 'KX074XK', 'generation_id' => 130, 'modification_id' => 9001, 'vin' => $vin,
    ]));

    $add('JF2GTHRCXMH28223')->assertStatus(422)->assertJsonPath('error_code', 'vin_invalid');
    $add('JF2GTHRCOMH282237')->assertStatus(422)->assertJsonPath('error_code', 'vin_invalid');
    expect(UserCar::query()->where('plate', 'KX074XK')->exists())->toBeFalse();
});

it('adds a car without VIN and colour as before', function () {
    fakeFocusCatalog();
    $token = auth('api')->login(makeUser('555 71 72 03'));

    $this->withToken($token)->postJson('/api/mycars/add', focus(['plate' => 'KX074XK', 'generation_id' => 130, 'modification_id' => 9001]))
        ->assertOk()->assertJsonPath('car.vin', null)->assertJsonPath('car.color', null);
});

it('edits and clears the VIN and colour of a saved car', function () {
    fakeFocusCatalog();
    $user = makeUser('555 71 72 04');
    $token = auth('api')->login($user);
    $this->withToken($token)->postJson('/api/mycars/add', focus(['plate' => 'KX074XK', 'generation_id' => 130, 'modification_id' => 9001]))->assertOk();
    $car = UserCar::query()->where('plate', 'KX074XK')->first();

    $this->withToken($token)->postJson("/api/mycars/{$car->id}/edit", ['vin' => 'JF2GTHRCXMH282237', 'color' => 'ნაცრისფერი'])->assertOk();
    expect($car->fresh()->vin)->toBe('JF2GTHRCXMH282237')->and($car->fresh()->color)->toBe('ნაცრისფერი');

    $this->withToken($token)->postJson("/api/mycars/{$car->id}/edit", ['color' => str_repeat('a', 41)])
        ->assertStatus(422)->assertJsonPath('error_code', 'color_invalid');
    $this->withToken($token)->postJson("/api/mycars/{$car->id}/edit", ['vin' => '', 'color' => ''])->assertOk();
    expect($car->fresh()->vin)->toBeNull()->and($car->fresh()->color)->toBeNull();
});
