<?php

use App\Models\SmsTemp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tymon\JWTAuth\Facades\JWTAuth;

// BE-01
it('rejects unauthenticated partner voucher calls', function () {
    $this->postJson('/api/partner/voucher/check', ['code' => 'ABCDEFGH'])->assertUnauthorized();
    $this->postJson('/api/partner/voucher/use', ['temp_code' => 1, 'code' => '123456'])->assertUnauthorized();
});

it('lets an authenticated partner reach the voucher routes', function () {
    DB::table('partners')->insert([
        'login' => 'partner1', 'password' => Hash::make('pw-test-123'), 'name' => 'P', 'description' => 'd',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $token = $this->postJson('/api/partner/login', ['login' => 'partner1', 'password' => 'pw-test-123'])
        ->assertOk()->json('access_token');
    $this->withToken($token)->postJson('/api/partner/voucher/check', ['code' => 'NOPE0000'])
        ->assertOk()->assertJson(['success' => false]);
});

// BE-06/02
it('never returns the registration OTP in the response', function () {
    Http::fake(['*' => Http::response(['Success' => true])]);
    $res = $this->postJson('/api/register', ['phone' => '+995555111222']);
    expect($res->getContent())->not->toContain('sms_code');
    $code = SmsTemp::first()->code;
    expect($res->getContent())->not->toContain('"'.$code.'"');
});

it('ships production-safe .env.example', function () {
    $env = file_get_contents(base_path('.env.example'));
    expect($env)->toContain('APP_ENV=production')->toContain('APP_DEBUG=false');
});

// BE-08
it('forbids deleting another user\'s package', function () {
    $owner = makeUser('+995555000001');
    $other = makeUser('+995555000002');
    DB::statement('PRAGMA defer_foreign_keys = ON');
    $id = DB::table('user_packages')->insertGetId([
        'user_id' => $owner->id, 'package_id' => 1, 'user_car_id' => 1, 'end_date' => now()->addMonth(), 'number_of_washes' => 1, 'qr_code' => 'QRTEST0001',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $token = JWTAuth::fromUser($other);
    $this->withToken($token)->deleteJson("/api/packages/{$id}/remove")->assertForbidden();
    expect(DB::table('user_packages')->where('id', $id)->exists())->toBeTrue();
});

// BE-12 (stage 1): the old temp_code+code / verify_code contract is gone; only a one-time setup_token works.
it('rejects the old numeric temp_code + raw OTP contract on set_password', function () {
    $user = makeUser('+995555000003');
    $sms = SmsTemp::create(['type' => 'verify', 'user_id' => $user->id, 'code' => '654321']);
    $this->postJson('/api/register/set_password', ['temp_code' => $sms->id, 'code' => '654321', 'password' => 'secret123'])->assertStatus(422);
    expect(Hash::check('secret123', $user->fresh()->password))->toBeFalse();
});

it('rejects the old verify_code contract on password reset', function () {
    $user = makeUser('+995555000005');
    SmsTemp::create(['type' => 'reset', 'user_id' => $user->id, 'code' => '333444']);
    $this->postJson('/api/change_password/verify_submit', ['verify_code' => '333444', 'password' => 'newpass1'])->assertStatus(422);
    expect(Hash::check('newpass1', $user->fresh()->password))->toBeFalse();
});

// BE-13
it('has no hard-coded passwords in code or seeders', function () {
    $files = [
        app_path('Http/Controllers/Api/AuthController.php'),
        database_path('seeders/DatabaseSeeder.php'),
    ];
    foreach ($files as $f) {
        expect(file_get_contents($f))->not->toMatch('/(admin123|manager123|client123|partner123|34rbb45i43jnv8493uh)/');
    }
});

// BE-15
it('registers sandbox/test routes outside production only', function () {
    expect(Route::has('payment.sandbox'))->toBeTrue();
    $web = file_get_contents(base_path('routes/web.php'));
    $api = file_get_contents(base_path('routes/api.php'));
    expect($web)->toMatch("/environment\\('production'\\)\\)\\s*\\{\\s*Route::get\\('\\/payment\\/sandbox'/");
    expect($api)->toMatch("/environment\\('production'\\)\\)\\s*\\{\\s*Route::get\\('\\/testpay'/");
});
