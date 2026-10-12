<?php

use App\Models\CorporateClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

// UX-09
it('fixes the promo typo idempotently and touches only that word', function () {
    DB::table('promos')->insert([
        'title' => 'ჭკკვიანი რეცხვა', 'description' => 'ჭკკვიანი და სწრაფი, ჭკვიანი', 'url' => 'https://x',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $migration = require database_path('migrations/2026_10_12_000004_fix_promo_typo.php');
    $migration->up();
    $migration->up();
    $promo = DB::table('promos')->first();
    expect($promo->title)->toBe('ჭკვიანი რეცხვა')
        ->and($promo->description)->toBe('ჭკვიანი და სწრაფი, ჭკვიანი')
        ->and($promo->url)->toBe('https://x');
});

// UX-30
function corporate(): CorporateClient
{
    return CorporateClient::forceCreate([
        'name' => 'Corp', 'api_token' => Str::random(40), 'username' => 'corpuser', 'password' => Hash::make('oldpass1'),
    ]);
}

it('updates the corporate username without the current password', function () {
    $c = corporate();
    $this->withSession(['corporate_client_id' => $c->id])
        ->post('/partner/account', ['username' => 'newname'])
        ->assertSessionHasNoErrors()->assertRedirect(route('partner.account'));
    expect($c->fresh()->username)->toBe('newname');
});

it('requires the correct current password to change the corporate password', function () {
    $c = corporate();
    $s = ['corporate_client_id' => $c->id];
    $this->withSession($s)->post('/partner/account', ['username' => 'corpuser', 'password' => 'newpass1', 'password_confirmation' => 'newpass1'])
        ->assertSessionHasErrors('current_password');
    $this->withSession($s)->post('/partner/account', ['username' => 'corpuser', 'current_password' => 'wrong', 'password' => 'newpass1', 'password_confirmation' => 'newpass1'])
        ->assertSessionHasErrors('current_password');
    expect(Hash::check('oldpass1', $c->fresh()->password))->toBeTrue();
    $this->withSession($s)->post('/partner/account', ['username' => 'corpuser', 'current_password' => 'oldpass1', 'password' => 'newpass1', 'password_confirmation' => 'newpass1'])
        ->assertSessionHasNoErrors();
    expect(Hash::check('newpass1', $c->fresh()->password))->toBeTrue();
});
