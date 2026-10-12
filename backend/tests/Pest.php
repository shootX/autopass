<?php

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

function makeUser(string $phone, int $role = \App\Models\User::ROLE_USER): \App\Models\User
{
    return \App\Models\User::create([
        'phone' => $phone,
        'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(20)),
        'role' => $role,
        'ref_code' => substr(md5($phone), 0, 5),
    ]);
}

