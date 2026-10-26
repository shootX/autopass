<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

require_once __DIR__.'/Support/corporate.php';

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

function makeUser(string $phone, int $role = User::ROLE_USER): User
{
    return User::forceCreate([
        'phone' => $phone,
        'password' => Hash::make(Str::random(20)),
        'role' => $role,
        'ref_code' => substr(md5($phone), 0, 5),
        'phone_verified_at' => now(),
    ]);
}

/** One-time approve token as GET /api/checkqr issues it (package approve accepts only this). */
function approveTokenFor(User $manager, int $packageId): string
{
    $token = bin2hex(random_bytes(20));
    Cache::put('package-approve:'.hash('sha256', $token), [
        'manager_id' => (int) $manager->id,
        'package_id' => $packageId,
    ], now()->addMinutes(10));

    return $token;
}

/** A Toyota Avensis (geoapi brand 501, model 7) built as a sedan and a wagon in 2014-2018, as an SUV from 2019. */
function fakeYearCatalog(array $generations = [
    ['id' => '71', 'name' => 'T27 Sedan', 'year_start' => 2012, 'year_end' => 2018, 'body_type' => 'Sedan'],
    ['id' => '72', 'name' => 'T27 Wagon', 'year_start' => 2014, 'year_end' => 2018, 'body_type' => 'Station wagon'],
    ['id' => '73', 'name' => 'T28', 'year_start' => 2019, 'year_end' => null, 'body_type' => 'Crossover'],
]): void {
    Cache::flush();
    config(['services.carapi.url' => 'https://geoapi.test/api/v1', 'services.carapi.secret' => 'geoapi_sk_test']);
    Http::fake([
        'geoapi.test/api/v1/models/7/generations*' => Http::response(['data' => $generations, 'meta' => ['total' => count($generations)]]),
        'geoapi.test/api/v1/brands/toyota/models*' => Http::response(['data' => [['id' => '7', 'name' => 'Avensis']], 'meta' => ['total' => 1]]),
        'geoapi.test/api/v1/brands*' => Http::response(['data' => [['id' => '501', 'name' => 'Toyota', 'slug' => 'toyota']], 'meta' => ['total' => 1]]),
    ]);
}
