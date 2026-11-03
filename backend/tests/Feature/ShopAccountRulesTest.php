<?php

// The third review, server side of the app points (2026-11-03): voucher limits, purchase ids and last days,
// the exact package end time, and a deleted account kept 90 days.

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\Package;
use App\Models\PackagePrice;
use App\Models\Partner;
use App\Models\SmsTemp;
use App\Models\User;
use App\Models\UserCar;
use App\Models\UserPackage;
use App\Models\UserPushToken;
use App\Models\UserVoucher;
use App\Models\Voucher;
use App\Models\VoucherCategory;
use App\Services\Sms\MockSmsGateway;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

function srPartner(string $login): array
{
    $partner = Partner::query()->forceCreate(['login' => $login, 'password' => Hash::make('pw-'.$login), 'name' => $login, 'description' => 'd']);

    return [$partner, JWTAuth::fromUser($partner)];
}

function srVoucher(array $extra = []): Voucher
{
    $category = VoucherCategory::query()->firstOrCreate(['name' => 'Fuel']);
    $voucher = Voucher::query()->create($extra + ['name' => 'Fuel -10%', 'description' => 'x', 'photo' => 'f.png',
        'category_id' => $category->id, 'price' => 10, 'percent' => 10, 'date_to' => now()->addMonth()->toDateString()]);

    return $voucher->fresh();
}

function srBuyer(string $phone, int $points = 100): User
{
    $user = makeUser($phone);
    $user->forceFill(['points' => $points])->save();

    return $user;
}

// 4 and 5. Limit per user, several at once, one id per purchase

it('sells several vouchers at once up to the limit per user, each with its own id and code', function () {
    $voucher = srVoucher(['user_limit' => 3]);
    $user = srBuyer('555 75 00 01');
    $buy = fn (int $qty) => $this->withToken(cwAs($user))->postJson('/api/vouchers/buy', ['voucher_id' => $voucher->id, 'qty' => $qty]);

    $buy(2)->assertJson(['success' => true, 'points' => 80]);
    expect(UserVoucher::query()->where('user_id', $user->id)->pluck('code')->unique())->toHaveCount(2);

    $shop = $this->withToken(cwAs($user))->getJson('/api/shop/home')->assertOk();
    expect($shop->json('vouchers.0'))->toMatchArray(['id' => $voucher->id, 'user_limit' => 3, 'owned' => 2, 'left' => 1]);
    $mine = collect($shop->json('my_vouchers'));
    expect($mine)->toHaveCount(2)
        ->and($mine->pluck('id')->sort()->values()->all())->toBe(UserVoucher::query()->orderBy('id')->pluck('id')->all())
        ->and($mine->pluck('voucher_id')->unique()->all())->toBe([$voucher->id])
        ->and($mine->pluck('code')->unique())->toHaveCount(2);

    $buy(2)->assertJson(['success' => false, 'message' => 'Voucher limit reached']);
    $buy(1)->assertJson(['success' => true, 'points' => 70]);
    $buy(1)->assertJson(['success' => false, 'message' => 'Voucher limit reached']);
    expect((int) $user->fresh()->points)->toBe(70)
        ->and($this->withToken(cwAs($user))->getJson('/api/vouchers')->json('vouchers.data.0.left'))->toBe(0);
});

it('sells one voucher per user unless the admin raises it, and never past the stock', function () {
    $one = srVoucher();
    expect($one->user_limit)->toBe(1);
    $user = srBuyer('555 75 00 02');
    $this->withToken(cwAs($user))->postJson('/api/vouchers/buy', ['voucher_id' => $one->id])->assertJson(['success' => true]);
    $this->withToken(cwAs($user))->postJson('/api/vouchers/buy', ['voucher_id' => $one->id])->assertJson(['success' => false, 'message' => 'Voucher limit reached']);

    $stock = srVoucher(['name' => 'Two left', 'user_limit' => 5, 'count' => 2]);
    $this->withToken(cwAs($user))->postJson('/api/vouchers/buy', ['voucher_id' => $stock->id, 'qty' => 2])->assertJson(['success' => true]);
    $other = srBuyer('555 75 00 03');
    $this->withToken(cwAs($other))->postJson('/api/vouchers/buy', ['voucher_id' => $stock->id])->assertJson(['success' => false, 'message' => 'Voucher limit reached']);
    expect((int) $other->fresh()->points)->toBe(100);
});

// 6. Last day, and disabling

it('stops selling a disabled voucher but lets the bought one be used until its last day', function () {
    [$partner, $partnerToken] = srPartner('sr-shop');
    $voucher = srVoucher(['user_limit' => 2]);
    $voucher->forceFill(['partner_id' => $partner->id])->save();
    $user = srBuyer('555 75 00 04');
    $this->withToken(cwAs($user))->postJson('/api/vouchers/buy', ['voucher_id' => $voucher->id])->assertJson(['success' => true]);

    $this->actingAs(cwAdmin())->delete('/dashboard/vouchers/'.$voucher->id.'/delete')->assertRedirect();

    $this->withToken(cwAs($user))->postJson('/api/vouchers/buy', ['voucher_id' => $voucher->id])->assertJson(['success' => false, 'message' => 'Voucher unavailable']);
    $shop = $this->withToken(cwAs($user))->getJson('/api/shop/home')->assertOk();
    expect($shop->json('vouchers'))->toBe([])->and($shop->json('my_vouchers'))->toHaveCount(1)
        ->and($this->withToken(cwAs($user))->getJson('/api/vouchers')->json('vouchers.data'))->toBe([])
        ->and($this->withToken(cwAs($user))->getJson('/api/vouchers/history')->json('vouchers.0.status'))->toBe('active');

    $code = UserVoucher::query()->value('code');
    cwAs($user); // a fresh guard for the partner's token
    $this->withToken($partnerToken)->postJson('/api/partner/voucher/check', ['code' => $code])->assertOk()->assertJson(['success' => true]);
    $sms = SmsTemp::query()->where('type', 'use_voucher')->latest('id')->first();
    $this->withToken($partnerToken)->postJson('/api/partner/voucher/use', ['temp_code' => $sms->public_id, 'code' => app(MockSmsGateway::class)->latestCode()])
        ->assertOk()->assertJson(['success' => true]);
});

it('takes a voucher past its last day out of the shop and refuses the bought one', function () {
    [$partner, $partnerToken] = srPartner('sr-old');
    $voucher = srVoucher(['user_limit' => 2]);
    $voucher->forceFill(['partner_id' => $partner->id])->save();
    $user = srBuyer('555 75 00 05');
    $this->withToken(cwAs($user))->postJson('/api/vouchers/buy', ['voucher_id' => $voucher->id])->assertJson(['success' => true]);
    $code = UserVoucher::query()->value('code');
    // A check started on its last day, finished after it.
    cwAs($user); // a fresh guard for the partner's token
    $this->withToken($partnerToken)->postJson('/api/partner/voucher/check', ['code' => $code])->assertJson(['success' => true]);
    $sms = SmsTemp::query()->where('type', 'use_voucher')->latest('id')->first();
    $smsCode = app(MockSmsGateway::class)->latestCode();

    $voucher->forceFill(['date_to' => now()->subDay()->toDateString()])->save();

    $this->withToken(cwAs($user))->postJson('/api/vouchers/buy', ['voucher_id' => $voucher->id])->assertJson(['success' => false, 'message' => 'Voucher expired']);
    $shop = $this->withToken(cwAs($user))->getJson('/api/shop/home')->assertOk();
    expect($shop->json('vouchers'))->toBe([])->and($shop->json('my_vouchers'))->toBe([])
        ->and($this->withToken(cwAs($user))->getJson('/api/vouchers/history')->json('vouchers.0.status'))->toBe('expired');
    cwAs($user);
    $this->withToken($partnerToken)->postJson('/api/partner/voucher/use', ['temp_code' => $sms->public_id, 'code' => $smsCode])
        ->assertStatus(422)->assertJsonPath('error_code', 'voucher_expired');
    $this->withToken($partnerToken)->postJson('/api/partner/voucher/check', ['code' => $code])
        ->assertStatus(422)->assertJsonPath('error_code', 'voucher_expired');
    expect(UserVoucher::query()->value('used_at'))->toBeNull();
});

it('needs a last day and a limit per user on every voucher the admin saves', function () {
    $admin = cwAdmin();
    $category = VoucherCategory::query()->create(['name' => 'Wash']);
    $fields = fn (array $extra) => $extra + ['category_id' => $category->id, 'name' => 'Polish', 'voucher_type' => 1, 'description' => 'polish -20%',
        'photo' => UploadedFile::fake()->image('v.png'), 'price' => 10, 'percent' => 20, 'footer_text' => 'f', 'footer_geo' => 'g'];

    $this->actingAs($admin)->post('/dashboard/vouchers/store', $fields(['user_limit' => 1]))->assertSessionHasErrors('date_to');
    $this->post('/dashboard/vouchers/store', $fields(['user_limit' => 1, 'date_to' => now()->subDay()->toDateString()]))->assertSessionHasErrors('date_to');
    $this->post('/dashboard/vouchers/store', $fields(['date_to' => now()->addWeek()->toDateString()]))->assertSessionHasErrors('user_limit');
    $this->post('/dashboard/vouchers/store', $fields(['user_limit' => 2, 'date_to' => now()->addWeek()->toDateString()]))->assertSessionHasNoErrors();

    $voucher = Voucher::query()->sole();
    expect($voucher->user_limit)->toBe(2)->and(substr((string) $voucher->date_to, 0, 10))->toBe(now()->addWeek()->toDateString());
    $this->get('/dashboard/vouchers')->assertOk()->assertSee(now()->addWeek()->toDateString());
});

// 11. Exact package end time

it('sends the exact package end time and gives no QR for a package a wash would refuse', function () {
    $brand = CarBrand::query()->firstOrCreate(['name' => 'Toyota']);
    $model = CarModel::query()->forceCreate(['name' => 'Camry', 'type' => 'sedan', 'brand_id' => $brand->id]);
    $driver = makeUser('555 75 00 06');
    $car = UserCar::query()->create(['user_id' => $driver->id, 'model_id' => $model->id, 'plate' => 'SR100SR', 'car_type' => 'sedan', 'body_status' => 'confirmed']);
    $package = Package::query()->firstOrCreate(['car_type' => 'sedan', 'count_washes' => 4]);
    $price = PackagePrice::query()->firstOrCreate(['package_id' => $package->id, 'month' => 1], ['price' => 40]);
    $endsAt = now()->addHours(2)->startOfMinute();
    $bought = UserPackage::query()->create(['user_id' => $driver->id, 'user_car_id' => $car->id, 'package_id' => $package->id, 'price_id' => $price->id,
        'start_date' => now()->subMonth(), 'end_date' => $endsAt->toDateTimeString(), 'number_of_washes' => 4, 'used_washes' => 0, 'qr_code' => Str::random(16)]);
    $qr = fn () => $this->withToken(cwAs($driver))->get('/api/packages/'.$bought->id.'/qr');

    $sent = $this->withToken(cwAs($driver))->getJson('/api/packages/my')->assertOk()->json('packages.0.ends_at');
    expect(Carbon::parse($sent)->equalTo($endsAt))->toBeTrue();
    $qr()->assertOk();

    // Later the same day: past the end time, though the date is still today.
    $this->travel(3)->hours();
    $qr()->assertStatus(422)->assertJsonPath('error_code', 'package_expired');

    $bought->forceFill(['end_date' => now()->addWeek()->toDateTimeString(), 'used_washes' => 4])->save();
    $qr()->assertStatus(422)->assertJsonPath('error_code', 'package_used_up');
});

// 16. The open QR screen learns that a wash spent its code

it('tells the app when a wash spent the code it shows', function () {
    $brand = CarBrand::query()->firstOrCreate(['name' => 'Toyota']);
    $model = CarModel::query()->forceCreate(['name' => 'Camry', 'type' => 'sedan', 'brand_id' => $brand->id]);
    $driver = makeUser('555 75 00 08');
    $car = UserCar::query()->create(['user_id' => $driver->id, 'model_id' => $model->id, 'plate' => 'SR300SR', 'car_type' => 'sedan', 'body_status' => 'confirmed']);
    $package = Package::query()->firstOrCreate(['car_type' => 'sedan', 'count_washes' => 4]);
    $bought = UserPackage::query()->create(['user_id' => $driver->id, 'user_car_id' => $car->id, 'package_id' => $package->id,
        'start_date' => now()->subDay(), 'end_date' => now()->addMonth(), 'number_of_washes' => 4, 'used_washes' => 0, 'qr_code' => Str::random(16)]);
    $manager = cwManager('555 75 00 09');
    $live = fn () => $this->withToken(cwAs($driver))->getJson('/api/packages/'.$bought->id.'/qr/live')->assertOk()->json('live');

    expect($live())->toBeFalse();
    $this->withToken(cwAs($driver))->get('/api/packages/'.$bought->id.'/qr')->assertOk();
    expect($live())->toBeTrue();
    $token = $this->withToken(cwAs($manager))->getJson('/api/checkqr/'.$bought->fresh()->qr_code)->assertOk()->json('approve_token');
    $this->withToken(cwAs($manager))->postJson('/api/package/'.$bought->id.'/approve', ['approve_token' => $token])->assertOk();
    expect($live())->toBeFalse();
    $this->withToken(cwAs(makeUser('555 75 00 10')))->getJson('/api/packages/'.$bought->id.'/qr/live')->assertNotFound();

    $fleet = cwFleet(5, 2, 'SR400SR');
    $companyLive = fn () => $this->withToken(cwAs($fleet['driver']))->getJson('/api/mycars/'.$fleet['userCar']->id.'/company-qr/live')->assertOk()->json('live');
    $qr = cwDriverQr($this, $fleet);
    expect($companyLive())->toBeTrue();
    $token = $this->withToken(cwAs($manager))->getJson('/api/checkqr/'.$qr)->assertOk()->json('approve_token');
    $this->withToken(cwAs($manager))->postJson('/api/corporate-wash/'.$fleet['car']->id.'/approve', ['approve_token' => $token])->assertOk();
    expect($companyLive())->toBeFalse();
});

// 12. Deleted account kept 90 days

it('keeps a deleted account 90 days and gives it back when the same phone signs in', function () {
    $fleet = cwFleet(5, 2, 'SR200SR');
    $driver = $fleet['driver'];
    $driver->forceFill(['name' => 'Nino', 'points' => 70, 'password' => Hash::make('driverpass'), 'phone_verified_at' => now()])->save();
    UserPushToken::query()->create(['user_id' => $driver->id, 'push_id' => str_repeat('b', 64), 'kind' => 'apns']);
    $wash = DB::table('car_washes')->insertGetId(['name' => 'W', 'address' => 'a', 'work_time_start' => '09:00', 'work_time_end' => '20:00',
        'location' => 'x', 'manager_id' => makeUser('555 75 00 98', User::ROLE_MANAGER)->id, 'created_at' => now(), 'updated_at' => now()]);
    $booking = Appointment::create(['user_id' => $driver->id, 'car_wash_id' => $wash, 'car_id' => $fleet['userCar']->id,
        'date' => now()->addDay()->toDateString(), 'time' => '10:00', 'qr_code' => Str::random(12), 'approved' => AppointmentStatus::Pending->value]);
    $token = cwAs($driver);

    $deleted = $this->withToken($token)->postJson('/api/account/delete')->assertOk()->assertJsonPath('restore_days', 90);
    expect(Carbon::parse($deleted->json('erase_at'))->isSameDay(now()->addDays(90)))->toBeTrue();

    // Signed out and quiet, with everything kept.
    $this->withToken($token)->getJson('/api/mycars')->assertUnauthorized();
    $kept = $driver->fresh();
    expect($kept->deletion_requested_at)->not->toBeNull()
        ->and($kept->name)->toBe('Nino')->and((int) $kept->points)->toBe(70)
        ->and(UserPushToken::query()->where('user_id', $driver->id)->exists())->toBeFalse()
        ->and((int) $booking->fresh()->approved)->toBe(AppointmentStatus::Cancelled->value)
        ->and($fleet['car']->fresh()->user_car_id)->toBe($fleet['userCar']->id);
    $this->artisan('accounts:erase-deleted')->assertSuccessful();
    expect($driver->fresh()->name)->toBe('Nino');

    // 89 days later the same phone signs in: the account is back.
    $this->travel(89)->days();
    cwAs($driver);
    $this->postJson('/api/login', ['phone' => $driver->phone, 'password' => 'driverpass'])->assertOk()->assertJsonPath('restored', true);
    expect($driver->fresh()->deletion_requested_at)->toBeNull()
        ->and(UserCar::query()->where('user_id', $driver->id)->count())->toBe(1);
    $this->travel(30)->days();
    $this->artisan('accounts:erase-deleted')->assertSuccessful();
    expect($driver->fresh()->name)->toBe('Nino');
});

it('erases a deleted account for good after 90 days', function () {
    $user = srBuyer('555 75 00 07');
    $user->forceFill(['name' => 'Gio', 'password' => Hash::make('userpass1'), 'phone_verified_at' => now()])->save();
    $phone = $user->phone;
    $this->withToken(cwAs($user))->postJson('/api/account/delete')->assertOk();

    $this->travel(91)->days();
    $this->artisan('accounts:erase-deleted')->assertSuccessful();

    $erased = $user->fresh();
    expect($erased->name)->toBeNull()->and($erased->getRawOriginal('phone'))->toStartWith('deleted-');
    cwAs($user);
    $this->postJson('/api/login', ['phone' => $phone, 'password' => 'userpass1'])->assertUnauthorized();
});
