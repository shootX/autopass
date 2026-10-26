<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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
