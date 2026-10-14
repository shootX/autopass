<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

function makeUser(string $phone, int $role = User::ROLE_USER): User
{
    return User::create([
        'phone' => $phone,
        'password' => Hash::make(Str::random(20)),
        'role' => $role,
        'ref_code' => substr(md5($phone), 0, 5),
        'phone_verified_at' => now(),
    ]);
}
