<?php

use App\Models\Partner;
use App\Models\SmsTemp;
use App\Models\User;
use App\Services\Sms\MockSmsGateway;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tymon\JWTAuth\Facades\JWTAuth;

beforeEach(function () {
    DB::statement('PRAGMA defer_foreign_keys = ON');
    Http::fake(['*' => Http::response(['Success' => true])]);
    Queue::fake();
});

function freshAuth(): void
{
    // Tests reuse one app: drop the guard and JWT singletons that cache the previous token/user.
    foreach (['tymon.jwt', 'tymon.jwt.auth'] as $id) {
        app()->forgetInstance($id);
    }
    JWTAuth::clearResolvedInstances();
    app('auth')->forgetGuards();
}

function row(string $table, array $data): int
{
    return DB::table($table)->insertGetId($data + ['created_at' => now(), 'updated_at' => now()]);
}

function washFor(User $manager): int
{
    return row('car_washes', [
        'name' => 'W'.$manager->id, 'address' => 'a', 'work_time_start' => '09:00', 'work_time_end' => '20:00',
        'location' => 'x', 'manager_id' => $manager->id,
    ]);
}

function userPackage(int $userId, int $carId, array $extra = []): int
{
    return row('user_packages', $extra + [
        'user_id' => $userId, 'package_id' => 1, 'user_car_id' => $carId, 'end_date' => now()->addMonth(),
        'number_of_washes' => 5, 'used_washes' => 0, 'qr_code' => 'QR'.uniqid(),
    ]);
}

function partnerToken(string $login): array
{
    $id = row('partners', ['login' => $login, 'password' => Hash::make('pw-'.$login), 'name' => $login, 'description' => 'd']);

    return [$id, JWTAuth::fromUser(Partner::find($id))];
}

// BE-04
it('exposes only display fields of the promo', function () {
    row('promos', ['title' => 'T', 'description' => 'D', 'url' => 'https://x']);
    $promo = $this->getJson('/api/promo')->assertOk()->json('promo');
    expect(array_keys($promo))->toEqualCanonicalizing(['title', 'description', 'url']);
});

// BE-05
it('no longer deletes via GET in the admin dashboard', function () {
    $this->get('/dashboard/vouchers/1/delete')->assertStatus(405);
    $this->get('/dashboard/clients/1/delete')->assertStatus(405);
    $this->get('/partner/cars/1/delete')->assertStatus(405);
});

// BE-09 / BE-11
it('returns 403 to non-managers on package approve', function () {
    $u = makeUser('+995555100002');
    $this->withToken(JWTAuth::fromUser($u))->postJson('/api/package/1/approve')->assertForbidden();
});

it('blocks approving against another branch appointment and records the wash on the own branch', function () {
    $mgr1 = makeUser('+995555100003', User::ROLE_MANAGER);
    $mgr2 = makeUser('+995555100004', User::ROLE_MANAGER);
    $client = makeUser('+995555100005');
    $wash1 = washFor($mgr1);
    washFor($mgr2);
    $car = row('user_cars', ['user_id' => $client->id, 'model_id' => 1, 'plate' => 'AA111AA']);
    $pkg = userPackage($client->id, $car);
    row('appointments', ['user_id' => $client->id, 'car_wash_id' => $wash1, 'car_id' => $car, 'date' => now()->toDateString(), 'time' => '10:00', 'qr_code' => 'A'.uniqid()]);

    $this->withToken(JWTAuth::fromUser($mgr2))->postJson("/api/package/{$pkg}/approve")->assertForbidden();
    expect(DB::table('user_packages')->find($pkg)->used_washes)->toBe(0);

    freshAuth();
    $this->withToken(JWTAuth::fromUser($mgr1))->postJson("/api/package/{$pkg}/approve")->assertOk();
    expect(DB::table('user_packages')->find($pkg)->used_washes)->toBe(1);
    $wash = DB::table('package_washes')->first();
    expect((int) $wash->car_wash_id)->toBe($wash1)->and((bool) $wash->walk_in)->toBeFalse();
});

it('allows walk-in approval without a booking and records it on the manager branch', function () {
    $mgr = makeUser('+995555100020', User::ROLE_MANAGER);
    $client = makeUser('+995555100021');
    $wash = washFor($mgr);
    $car = row('user_cars', ['user_id' => $client->id, 'model_id' => 1, 'plate' => 'AA555AA']);
    $pkg = userPackage($client->id, $car);
    $this->withToken(JWTAuth::fromUser($mgr))->postJson("/api/package/{$pkg}/approve")->assertOk();
    $rec = DB::table('package_washes')->where('user_package_id', $pkg)->first();
    expect((int) $rec->car_wash_id)->toBe($wash)->and((bool) $rec->walk_in)->toBeTrue()->and((int) $rec->manager_id)->toBe($mgr->id);
});

it('refuses approving an exhausted package', function () {
    $mgr = makeUser('+995555100006', User::ROLE_MANAGER);
    $client = makeUser('+995555100007');
    $wash = washFor($mgr);
    $car = row('user_cars', ['user_id' => $client->id, 'model_id' => 1, 'plate' => 'AA222AA']);
    $pkg = userPackage($client->id, $car, ['used_washes' => 5]);
    row('appointments', ['user_id' => $client->id, 'car_wash_id' => $wash, 'car_id' => $car, 'date' => now()->toDateString(), 'time' => '10:00', 'qr_code' => 'A'.uniqid()]);
    $this->withToken(JWTAuth::fromUser($mgr))->postJson("/api/package/{$pkg}/approve")->assertStatus(422);
});

// BE-10
it('reports failure when editing or removing a car you do not own', function () {
    $owner = makeUser('+995555100008');
    $other = makeUser('+995555100009');
    $car = row('user_cars', ['user_id' => $owner->id, 'model_id' => 1, 'plate' => 'AA333AA']);
    $t = JWTAuth::fromUser($other);
    $this->withToken($t)->postJson("/api/mycars/{$car}/edit", ['plate' => 'BB333BB'])->assertNotFound()->assertJson(['success' => false]);
    $this->withToken($t)->deleteJson("/api/mycars/{$car}/remove")->assertNotFound()->assertJson(['success' => false]);
    expect(DB::table('user_cars')->find($car)->plate)->toBe('AA333AA');
    freshAuth();
    $this->withToken(JWTAuth::fromUser($owner))->postJson("/api/mycars/{$car}/edit", ['plate' => 'BB333BB', 'user_id' => $other->id])->assertOk();
    expect(DB::table('user_cars')->find($car))->plate->toBe('BB333BB')->user_id->toBe($owner->id);
});

// BE-14
it('does not leak exception messages from register', function () {
    Http::fake(fn () => throw new RuntimeException('SECRET-INTERNAL-DETAIL'));
    $res = $this->postJson('/api/register', ['phone' => '+995555100010']);
    expect($res->getContent())->not->toContain('SECRET-INTERNAL-DETAIL');
});

// BE-16
it('falls back to the local car catalog when CarAPI is not configured', function () {
    Config::set('services.carapi.secret', '');
    $brand = row('car_brands', ['name' => 'Toyota']);
    row('car_models', ['name' => 'Prius', 'type' => 'sedan', 'brand_id' => $brand]);
    $t = JWTAuth::fromUser(makeUser('+995555100011'));
    $brands = $this->withToken($t)->getJson('/api/cars/brands')->assertOk()->json('brands');
    expect(collect($brands)->pluck('name'))->toContain('Toyota');
    $this->withToken($t)->getJson("/api/cars/brands/{$brand}/models")->assertOk()->assertJsonPath('models.0.name', 'Prius');
});

// BE-17
it('does not expose client PII in checkqr', function () {
    $mgr = makeUser('+995555100012', User::ROLE_MANAGER);
    $client = makeUser('+995555100013');
    $car = row('user_cars', ['user_id' => $client->id, 'model_id' => 1, 'plate' => 'AA444AA']);
    userPackage($client->id, $car, ['qr_code' => 'QRPII0001']);
    $user = $this->withToken(JWTAuth::fromUser($mgr))->getJson('/api/checkqr/QRPII0001')->assertOk()->json('package.user');
    expect($user)->not->toHaveKeys(['phone', 'email', 'date_of_birth', 'sex', 'points', 'ref_code']);
});

// Rate limiting
it('throttles login attempts', function () {
    makeUser('+995555100014');
    foreach (range(1, 5) as $i) {
        $this->postJson('/api/login', ['phone' => '+995555100014', 'password' => 'wrongpass']);
    }
    $this->postJson('/api/login', ['phone' => '+995555100014', 'password' => 'wrongpass'])->assertStatus(429);
});

it('throttles OTP endpoints', function () {
    foreach (range(1, 5) as $i) {
        $this->postJson('/api/register/verify', ['temp_code' => 1, 'code' => '000000']);
    }
    $this->postJson('/api/register/verify', ['temp_code' => 1, 'code' => '000000'])->assertStatus(429);
});

// CORS
it('only allows configured CORS origins', function () {
    Config::set('cors.allowed_origins', ['https://app.autopass.ge']);
    $this->getJson('/api/promo', ['Origin' => 'https://evil.example'])
        ->assertHeaderMissing('Access-Control-Allow-Credentials')
        ->assertHeader('Access-Control-Allow-Origin', 'https://app.autopass.ge'); // never echoes the foreign origin or *
    $this->getJson('/api/promo', ['Origin' => 'https://app.autopass.ge'])
        ->assertHeader('Access-Control-Allow-Origin', 'https://app.autopass.ge');
});

// Vouchers tied to partner
it('lets only the owning partner check and use a voucher', function () {
    [$p1, $t1] = partnerToken('partner-one');
    [, $t2] = partnerToken('partner-two');
    $client = makeUser('+995555100015');
    $cat = row('voucher_categories', ['name' => 'c']);
    $v = row('vouchers', ['name' => 'v', 'description' => 'd', 'photo' => 'p', 'category_id' => $cat, 'price' => 1, 'partner_id' => $p1]);
    $vNone = row('vouchers', ['name' => 'v2', 'description' => 'd', 'photo' => 'p', 'category_id' => $cat, 'price' => 1]);
    $uv = row('user_vouchers', ['user_id' => $client->id, 'voucher_id' => $v, 'code' => 'CODE0001']);
    row('user_vouchers', ['user_id' => $client->id, 'voucher_id' => $vNone, 'code' => 'CODE0002']);

    freshAuth();
    $this->withToken($t2)->postJson('/api/partner/voucher/check', ['code' => 'CODE0001'])->assertForbidden();
    freshAuth();
    $this->withToken($t1)->postJson('/api/partner/voucher/check', ['code' => 'CODE0002'])->assertForbidden();
    freshAuth();
    $this->withToken($t1)->postJson('/api/partner/voucher/check', ['code' => 'CODE0001'])->assertOk()->assertJson(['success' => true]);

    $sms = SmsTemp::where('user_voucher_id', $uv)->first();
    $code = app(MockSmsGateway::class)->latestCode();
    freshAuth();
    $this->withToken($t2)->postJson('/api/partner/voucher/use', ['temp_code' => $sms->public_id, 'code' => $code])->assertForbidden();
    expect(DB::table('user_vouchers')->find($uv)->used_at)->toBeNull();
    freshAuth();
    $this->withToken($t1)->postJson('/api/partner/voucher/use', ['temp_code' => $sms->public_id, 'code' => $code])->assertOk()->assertJson(['success' => true]);
    $row = DB::table('user_vouchers')->find($uv);
    expect($row->used_at)->not->toBeNull()->and((int) $row->used_by_partner_id)->toBe($p1);
});

// Admin ban (decision d)
it('bans clients only via POST', function () {
    $this->get('/dashboard/clients/1/ban')->assertStatus(405);
    $admin = makeUser('+995555100030', User::ROLE_ADMIN);
    $client = makeUser('+995555100031');
    $this->actingAs($admin)->post("/dashboard/clients/{$client->id}/ban")->assertRedirect();
    expect((bool) $client->fresh()->ban)->toBeTrue();
});

it('ships the CORS origin default in .env.example', function () {
    expect(file_get_contents(base_path('.env.example')))->toContain('CORS_ALLOWED_ORIGINS=https://wash.socialsave.cc');
});

// BE-18
it('burns the voucher SMS code after 5 wrong attempts', function () {
    [$p1, $t1] = partnerToken('partner-bf');
    $client = makeUser('+995555100032');
    $cat = row('voucher_categories', ['name' => 'c']);
    $v = row('vouchers', ['name' => 'v', 'description' => 'd', 'photo' => 'p', 'category_id' => $cat, 'price' => 1, 'partner_id' => $p1]);
    $uv = row('user_vouchers', ['user_id' => $client->id, 'voucher_id' => $v, 'code' => 'CODEBF01']);
    $this->withToken($t1)->postJson('/api/partner/voucher/check', ['code' => 'CODEBF01'])->assertOk();
    $sms = SmsTemp::where('user_voucher_id', $uv)->first();
    $right = app(MockSmsGateway::class)->latestCode();
    $wrong = $right === '000000' ? '111111' : '000000';
    foreach (range(1, 5) as $i) {
        $this->withToken($t1)->postJson('/api/partner/voucher/use', ['temp_code' => $sms->public_id, 'code' => $wrong])->assertJson(['success' => false]);
    }
    $this->withToken($t1)->postJson('/api/partner/voucher/use', ['temp_code' => $sms->public_id, 'code' => $right])->assertStatus(422);
    expect(SmsTemp::find($sms->id)->consumed_at)->not->toBeNull();
    expect(DB::table('user_vouchers')->find($uv)->used_at)->toBeNull();
});

// BE-19
it('hides used vouchers from the client and refuses re-checking them', function () {
    [$p1, $t1] = partnerToken('partner-used');
    $client = makeUser('+995555100033');
    $cat = row('voucher_categories', ['name' => 'c']);
    $v = row('vouchers', ['name' => 'v', 'description' => 'd', 'photo' => 'p', 'category_id' => $cat, 'price' => 1, 'partner_id' => $p1]);
    row('user_vouchers', ['user_id' => $client->id, 'voucher_id' => $v, 'code' => 'USED0001', 'used_at' => now()]);
    expect($client->vouchers()->count())->toBe(0);
    $this->withToken($t1)->postJson('/api/partner/voucher/check', ['code' => 'USED0001'])->assertJson(['success' => false]);
});
