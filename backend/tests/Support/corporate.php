<?php

// Company car fixtures shared by the corporate wash and discount tests.

use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\CorporateClient;
use App\Models\FleetCar;
use App\Models\User;
use App\Models\UserCar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

/** @return array{corp: CorporateClient, car: FleetCar, driver: User, userCar: UserCar} */
function cwFleet(int $pool = 0, int $onCar = 0, string $plate = 'AA123BB', ?CorporateClient $corp = null): array
{
    $brand = CarBrand::query()->firstOrCreate(['name' => 'Toyota']);
    $model = CarModel::query()->where('name', 'Camry')->first()
        ?? CarModel::query()->forceCreate(['name' => 'Camry', 'type' => 'sedan', 'brand_id' => $brand->id]);
    $corp ??= CorporateClient::forceCreate([
        'name' => 'Wash Co', 'api_token' => CorporateClient::makeToken(), 'username' => 'washco', 'password' => Hash::make('corppass1'),
        'password_must_change' => false, 'session_version' => 1, 'washes_balance' => $pool,
    ]);
    $phone = '555 '.implode(' ', str_split(substr(sprintf('%06d', crc32($plate)), -6), 2));
    $driver = makeUser($phone);
    $userCar = UserCar::query()->create(['user_id' => $driver->id, 'model_id' => $model->id, 'plate' => $plate]);
    $car = FleetCar::query()->create(['corporate_client_id' => $corp->id, 'plate' => $plate, 'brand' => 'Toyota', 'model' => 'Camry', 'source' => 'manual']);
    $car->forceFill([
        'status' => FleetCar::STATUS_ACTIVE, 'driver_phone' => $phone, 'user_id' => $driver->id, 'user_car_id' => $userCar->id,
        'activated_at' => now(), 'washes_left' => $onCar,
    ])->save();

    return ['corp' => $corp, 'car' => $car, 'driver' => $driver, 'userCar' => $userCar];
}

function cwManager(string $phone = '555 80 00 01'): User
{
    $manager = makeUser($phone, User::ROLE_MANAGER);
    DB::table('car_washes')->insert([
        'name' => 'Vake', 'address' => 'a', 'work_time_start' => '09:00', 'work_time_end' => '20:00', 'location' => 'x',
        'manager_id' => $manager->id, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return $manager;
}

function cwAs(User $user): string
{
    foreach (['tymon.jwt', 'tymon.jwt.auth'] as $id) {
        app()->forgetInstance($id);
    }
    JWTAuth::clearResolvedInstances();
    app('auth')->forgetGuards();

    return JWTAuth::fromUser($user);
}

function cwAdmin(): User
{
    return User::query()->where('role', User::ROLE_ADMIN)->first() ?? makeUser('555 80 00 99', User::ROLE_ADMIN);
}

/** The code inside the driver's SVG is the fleet car's current qr_code. */
function cwDriverQr($test, array $fleet): string
{
    $test->withToken(cwAs($fleet['driver']))->get('/api/mycars/'.$fleet['userCar']->id.'/company-qr')
        ->assertOk()->assertHeader('Content-Type', 'image/svg+xml')->assertHeader('X-QR-Expires');

    return (string) $fleet['car']->fresh()->qr_code;
}
