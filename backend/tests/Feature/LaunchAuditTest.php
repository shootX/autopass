<?php

// The 2026-11-03 launch audit: QR reuse, purchase history kept, price edits, promo limits, plates,
// account erase, admin deletes, payment reconciliation and QR washes in reports.

use App\Models\Appointment;
use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\CorporateClient;
use App\Models\CorporateWash;
use App\Models\FleetCar;
use App\Models\Package;
use App\Models\PackagePrice;
use App\Models\PackageWash;
use App\Models\PromoCode;
use App\Models\TbcPayment;
use App\Models\User;
use App\Models\UserCar;
use App\Models\UserPackage;
use App\Models\UserPushToken;
use App\Services\CorporateQr;
use App\Services\CorporateWashes;
use App\Services\Payments\FlittCheckout;
use App\Services\Payments\SettleTbcPayment;
use App\Services\QrWashReport;
use App\Support\AppSettings;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery\MockInterface;

/** A driver with a confirmed sedan and a running 4-wash package on it. */
function auditDriver(string $phone = '555 73 00 01', string $plate = 'LA100LA'): array
{
    $brand = CarBrand::query()->firstOrCreate(['name' => 'Toyota']);
    $model = CarModel::query()->where('name', 'Camry')->first()
        ?? CarModel::query()->forceCreate(['name' => 'Camry', 'type' => 'sedan', 'brand_id' => $brand->id]);
    $driver = makeUser($phone);
    $car = UserCar::query()->create(['user_id' => $driver->id, 'model_id' => $model->id, 'plate' => $plate,
        'car_type' => 'sedan', 'body_status' => 'confirmed', 'vin' => 'JT'.strtoupper(Str::random(15))]);
    $package = Package::query()->firstOrCreate(['car_type' => 'sedan', 'count_washes' => 4]);
    $price = PackagePrice::query()->firstOrCreate(['package_id' => $package->id, 'month' => 1], ['price' => 40]);
    $bought = UserPackage::query()->create(['user_id' => $driver->id, 'user_car_id' => $car->id, 'package_id' => $package->id,
        'price_id' => $price->id, 'start_date' => now()->subDay(), 'end_date' => now()->addMonth(),
        'number_of_washes' => 4, 'used_washes' => 0, 'qr_code' => Str::random(16)]);

    return compact('driver', 'car', 'package', 'price', 'bought', 'model', 'brand');
}

/** What the app does to show the package QR; returns the code inside it. */
function auditShowQr($test, array $f): string
{
    $test->withToken(cwAs($f['driver']))->get('/api/packages/'.$f['bought']->id.'/qr')
        ->assertOk()->assertHeader('X-QR-Expires');

    return (string) $f['bought']->fresh()->qr_code;
}

function auditFlitt(): MockInterface
{
    $flitt = Mockery::mock(FlittCheckout::class)->makePartial();
    $flitt->shouldReceive('checkoutUrl')->andReturnUsing(fn (TbcPayment $p) => 'https://pay.example/'.$p->merchant_payment_id);
    app()->instance(FlittCheckout::class, $flitt);

    return $flitt;
}

// 1. QR

it('gives a package QR that lasts five minutes and is spent by a wash', function () {
    $f = auditDriver();
    $manager = cwManager('555 73 00 09');

    // Bought but never shown: nothing to scan.
    $this->withToken(cwAs($manager))->getJson('/api/checkqr/'.$f['bought']->qr_code)->assertStatus(410)
        ->assertJsonPath('error_code', 'qr_expired');

    $qr = auditShowQr($this, $f);
    $this->travel(6)->minutes();
    $this->withToken(cwAs($manager))->getJson('/api/checkqr/'.$qr)->assertStatus(410)->assertJsonPath('error_code', 'qr_expired');

    // A new code from the phone works; showing it again replaces the older one.
    $old = auditShowQr($this, $f);
    $qr = auditShowQr($this, $f);
    expect($qr)->not->toBe($old);
    $this->withToken(cwAs($manager))->getJson('/api/checkqr/'.$old)->assertNotFound();
    $token = $this->withToken(cwAs($manager))->getJson('/api/checkqr/'.$qr)->assertOk()->json('approve_token');

    $approve = $this->withToken(cwAs($manager))->postJson('/api/package/'.$f['bought']->id.'/approve', ['approve_token' => $token])->assertOk();
    expect($approve->json('package'))->not->toHaveKey('qr_code');
    // The scanned code is gone: a photo of it gives no second approve token.
    $this->withToken(cwAs($manager))->getJson('/api/checkqr/'.$qr)->assertNotFound();
    expect($f['bought']->fresh()->used_washes)->toBe(1);
});

it('gives one wash per scanned package code, however many tokens were taken from it', function () {
    $f = auditDriver('555 73 00 21', 'LC100LC');
    $manager = cwManager('555 73 00 22');
    $qr = auditShowQr($this, $f);
    // Scanning the same code three times before approving gives three tokens...
    $tokens = collect(range(1, 3))->map(fn () => $this->withToken(cwAs($manager))->getJson('/api/checkqr/'.$qr)->assertOk()->json('approve_token'));
    $approve = fn (string $token) => $this->withToken(cwAs($manager))->postJson('/api/package/'.$f['bought']->id.'/approve', ['approve_token' => $token]);

    // ...but only the first one washes: the wash spends the code and every token given for it.
    $approve($tokens[0])->assertOk();
    $approve($tokens[1])->assertForbidden()->assertJsonPath('error_code', 'scan_again');
    $approve($tokens[2])->assertForbidden();
    expect($f['bought']->fresh()->used_washes)->toBe(1)
        ->and(PackageWash::query()->count())->toBe(1);

    // A token for a code the app has since replaced, or one that ran out, washes nothing either.
    $qr = auditShowQr($this, $f);
    $stale = $this->withToken(cwAs($manager))->getJson('/api/checkqr/'.$qr)->json('approve_token');
    auditShowQr($this, $f);
    $approve($stale)->assertForbidden()->assertJsonPath('error_code', 'scan_again');
    $qr = auditShowQr($this, $f);
    $late = $this->withToken(cwAs($manager))->getJson('/api/checkqr/'.$qr)->json('approve_token');
    $this->travel(UserPackage::QR_TTL_SECONDS + 1)->seconds();
    $approve($late)->assertForbidden();
    expect($f['bought']->fresh()->used_washes)->toBe(1);
});

it('gives one wash per scanned company car code, however many tokens were taken from it', function () {
    $fleet = cwFleet(10, 5, 'LC200LC');
    $manager = cwManager('555 73 00 23');
    $qr = cwDriverQr($this, $fleet);
    $tokens = collect(range(1, 3))->map(fn () => $this->withToken(cwAs($manager))->getJson('/api/checkqr/'.$qr)->assertOk()->json('approve_token'));
    $approve = fn (string $token) => $this->withToken(cwAs($manager))->postJson('/api/corporate-wash/'.$fleet['car']->id.'/approve', ['approve_token' => $token]);

    $approve($tokens[0])->assertOk();
    $approve($tokens[1])->assertForbidden()->assertJsonPath('reason', 'scan_again');
    $approve($tokens[2])->assertForbidden();
    expect($fleet['car']->fresh()->washes_left)->toBe(4)
        ->and(CorporateWash::query()->count())->toBe(1)
        ->and($fleet['corp']->fresh()->washes_balance)->toBe(9);

    // The driver showing a new code voids a token taken from the older one.
    $qr = cwDriverQr($this, $fleet);
    $stale = $this->withToken(cwAs($manager))->getJson('/api/checkqr/'.$qr)->json('approve_token');
    cwDriverQr($this, $fleet);
    $approve($stale)->assertForbidden();
    expect($fleet['car']->fresh()->washes_left)->toBe(4);
});

// 2. History kept

it('does not remove a car with a running package and keeps the history of one removed later', function () {
    $f = auditDriver('555 73 00 02', 'LA200LA');
    $token = cwAs($f['driver']);

    $this->withToken($token)->deleteJson('/api/mycars/'.$f['car']->id.'/remove')->assertStatus(422)
        ->assertJsonPath('error_code', 'car_has_package');

    // A payment in progress for the car blocks it too.
    $f['bought']->forceFill(['used_washes' => 4])->save();
    $payment = TbcPayment::query()->create(['merchant_payment_id' => 'PLA200', 'user_id' => $f['driver']->id, 'type' => 'package',
        'amount' => 40, 'currency' => 'GEL', 'status' => 'pending', 'payload' => ['car_id' => $f['car']->id, 'package_id' => $f['package']->id]]);
    $this->withToken($token)->deleteJson('/api/mycars/'.$f['car']->id.'/remove')->assertStatus(422);

    $payment->update(['status' => 'expired']);
    $manager = cwManager('555 73 00 20');
    PackageWash::query()->create(['user_package_id' => $f['bought']->id, 'manager_id' => $manager->id,
        'car_wash_id' => (int) DB::table('car_washes')->where('manager_id', $manager->id)->value('id')]);
    $this->withToken($token)->deleteJson('/api/mycars/'.$f['car']->id.'/remove')->assertOk();

    $this->assertSoftDeleted('user_cars', ['id' => $f['car']->id]);
    expect(UserCar::query()->where('plate', 'LA200LA')->exists())->toBeFalse()
        ->and(UserPackage::query()->whereKey($f['bought']->id)->exists())->toBeTrue()
        ->and($f['bought']->fresh()->car?->plate)->toBe('LA200LA')
        ->and(PackageWash::query()->where('user_package_id', $f['bought']->id)->count())->toBe(1);
});

it('refuses removing a company car from the app', function () {
    $fleet = cwFleet(0, 0, 'LA210LA');
    $this->withToken(cwAs($fleet['driver']))->deleteJson('/api/mycars/'.$fleet['userCar']->id.'/remove')->assertForbidden();
    expect($fleet['userCar']->fresh())->not->toBeNull();
});

it('keeps bought packages when the admin takes a package off sale, and still grants one being paid', function () {
    $f = auditDriver('555 73 00 03', 'LA300LA');
    $payment = TbcPayment::query()->create(['merchant_payment_id' => 'PLA300', 'user_id' => $f['driver']->id, 'type' => 'package',
        'amount' => 40, 'currency' => 'GEL', 'status' => 'pending',
        'payload' => ['car_id' => $f['car']->id, 'package_id' => $f['package']->id, 'price_id' => $f['price']->id, 'month' => 1, 'washes' => 4]]);

    $this->actingAs(cwAdmin())->delete(route('admin.packages.delete', $f['package']))->assertRedirect();

    $this->assertSoftDeleted('packages', ['id' => $f['package']->id]);
    expect($f['bought']->fresh()->package?->id)->toBe($f['package']->id);
    $this->withToken(cwAs($f['driver']))->getJson('/api/packages/my')->assertOk()->assertJsonCount(1, 'packages');

    app(SettleTbcPayment::class)->grantNow($payment);
    expect($payment->fresh()->status)->toBe('succeeded')
        ->and(UserPackage::query()->where('tbc_payment_id', $payment->id)->value('package_id'))->toBe($f['package']->id);
});

it('keeps wash records when a branch is removed', function () {
    $f = auditDriver('555 73 00 04', 'LA400LA');
    $manager = cwManager('555 73 00 08');
    $washId = (int) DB::table('car_washes')->where('manager_id', $manager->id)->value('id');
    PackageWash::query()->create(['user_package_id' => $f['bought']->id, 'car_wash_id' => $washId, 'manager_id' => $manager->id]);

    $this->actingAs(cwAdmin())->delete(route('admin.washings.delete', $washId));

    $this->assertSoftDeleted('car_washes', ['id' => $washId]);
    expect(PackageWash::query()->sole()->washing?->id)->toBe($washId);
});

// 3. Price edit

it('edits package prices in place, and grants a payment for a month no longer sold', function () {
    $f = auditDriver('555 73 00 05', 'LA500LA');
    $this->actingAs(cwAdmin());
    $prices = ['type' => 'washes', 'car_type' => 'sedan', 'count_washes' => 4, 'price1' => 40, 'price2' => 100, 'price3' => 190, 'price4' => 350];
    $this->post(route('admin.packages.edit_save', $f['package']), $prices)->assertSessionHasNoErrors();
    $ids = $f['package']->prices()->pluck('id', 'month')->all();
    $sixMonths = $ids[6];
    $payment = TbcPayment::query()->create(['merchant_payment_id' => 'PLA500', 'user_id' => $f['driver']->id, 'type' => 'package',
        'amount' => 190, 'currency' => 'GEL', 'status' => 'pending',
        'payload' => ['car_id' => $f['car']->id, 'package_id' => $f['package']->id, 'price_id' => $sixMonths, 'month' => 6, 'washes' => 4]]);

    $this->post(route('admin.packages.edit_save', $f['package']), ['price1' => 45] + $prices)->assertSessionHasNoErrors();
    expect($f['package']->prices()->pluck('id', 'month')->all())->toBe($ids)
        ->and((float) PackagePrice::query()->find($ids[1])->price)->toBe(45.0)
        ->and($f['bought']->fresh()->price_id)->toBe($ids[1]);

    // The six-month row goes away (a discount package sells one month only); the paid order is still granted.
    $this->post(route('admin.packages.edit_save', $f['package']), ['type' => 'discount', 'car_type' => 'sedan', 'count_washes' => 0,
        'full_price' => 30, 'percent' => 20, 'price_discount' => 25])->assertSessionHasNoErrors();
    expect(PackagePrice::query()->find($sixMonths))->toBeNull();
    app(SettleTbcPayment::class)->grantNow($payment);
    $granted = UserPackage::query()->where('tbc_payment_id', $payment->id)->sole();
    expect($granted->price_id)->toBeNull()->and(Carbon::parse($granted->end_date)->greaterThan(now()->addMonths(5)))->toBeTrue();
});

it('does not reuse a package checkout opened at an older price', function () {
    $f = auditDriver('555 73 00 06', 'LA600LA');
    $f['bought']->forceFill(['used_washes' => 4])->save();
    auditFlitt();
    $token = cwAs($f['driver']);
    $buy = ['car_id' => $f['car']->id, 'package_id' => $f['package']->id, 'sub_term' => 1, 'renewal' => false];

    $first = $this->withToken($token)->postJson('/api/packages/buy', $buy)->assertOk()->json('url');
    expect($this->withToken($token)->postJson('/api/packages/buy', $buy)->json('url'))->toBe($first);
    $f['price']->update(['price' => 50]);
    $second = $this->withToken($token)->postJson('/api/packages/buy', $buy)->assertOk()->json('url');
    expect($second)->not->toBe($first)
        ->and((float) TbcPayment::query()->latest('id')->value('amount'))->toBe(50.0);
});

// 4. Promo limit

it('holds a discount code for an open payment so its limit cannot be stretched', function () {
    auditFlitt();
    AppSettings::store(['point_price' => 1, 'point_discount' => 0]);
    PromoCode::query()->create(['code' => 'ONCE', 'kind' => 'discount', 'value' => 90, 'max_uses' => 1]);
    $a = cwAs(makeUser('555 73 00 11'));

    $url = $this->withToken($a)->postJson('/api/points/buy', ['amount' => 50, 'promo_code' => 'ONCE'])->assertOk()->json('url');
    // The same page again; another amount waits for the open one.
    expect($this->withToken($a)->postJson('/api/points/buy', ['amount' => 50, 'promo_code' => 'ONCE'])->assertOk()->json('url'))->toBe($url);
    $this->withToken($a)->postJson('/api/points/buy', ['amount' => 60, 'promo_code' => 'ONCE'])->assertStatus(422)
        ->assertJsonPath('code', 'promo_pending');
    expect(TbcPayment::query()->count())->toBe(1);

    // Its only use is held: nobody else can open a second payment with it.
    $b = cwAs(makeUser('555 73 00 12'));
    $this->withToken($b)->postJson('/api/points/buy', ['amount' => 50, 'promo_code' => 'ONCE'])->assertStatus(422)
        ->assertJsonPath('code', 'promo_used_up');

    // An old unresolved page may already have been paid: age alone never frees the code.
    $this->travel(TbcPayment::SETTLE_MINUTES + 1)->minutes();
    $this->withToken($b)->postJson('/api/points/buy', ['amount' => 50, 'promo_code' => 'ONCE'])->assertStatus(422);
});

// 7. Plate

it('refuses a plate another car has and a plate change while a package runs', function () {
    $f = auditDriver('555 73 00 13', 'LA700LA');
    auditDriver('555 73 00 14', 'LA701LA');
    $token = cwAs($f['driver']);

    $this->withToken($token)->postJson('/api/mycars/'.$f['car']->id.'/edit', ['plate' => 'LA701LA'])->assertStatus(422)
        ->assertJsonPath('error_code', 'car_has_package');
    $f['bought']->forceFill(['end_date' => now()->subDay()])->save();
    $this->withToken($token)->postJson('/api/mycars/'.$f['car']->id.'/edit', ['plate' => 'LA701LA'])->assertStatus(400)
        ->assertJsonPath('error_code', 'plate_exists');
    $this->withToken($token)->postJson('/api/mycars/'.$f['car']->id.'/edit', ['plate' => 'LA702LA'])->assertOk();
    expect($f['car']->fresh()->plate)->toBe('LA702LA');
});

// 8. Account delete

it('erases an account: personal data, cars, push tokens and the company link go, the history stays', function () {
    $fleet = cwFleet(5, 2, 'LA800LA');
    $driver = $fleet['driver'];
    $driver->forceFill(['name' => 'Nino', 'email' => 'nino@example.test'])->save();
    UserPushToken::query()->create(['user_id' => $driver->id, 'push_id' => str_repeat('a', 64), 'kind' => 'apns']);
    $f = auditDriver('555 73 00 15', 'LA801LA');
    $f['bought']->forceFill(['user_id' => $driver->id])->save();
    $f['car']->forceFill(['user_id' => $driver->id])->save();
    $token = cwAs($driver);

    $this->withToken($token)->postJson('/api/account/delete')->assertOk();
    // Kept 90 days for a sign-in to bring it back (2026-11-03), then erased.
    $this->travel(91)->days();
    $this->artisan('accounts:erase-deleted')->assertSuccessful();

    $erased = User::query()->find($driver->id);
    expect($erased->name)->toBeNull()->and($erased->email)->toBeNull()
        ->and($erased->getRawOriginal('phone'))->toStartWith('deleted-')
        ->and(UserPushToken::query()->where('user_id', $driver->id)->exists())->toBeFalse()
        ->and(UserCar::query()->where('user_id', $driver->id)->exists())->toBeFalse()
        ->and(UserCar::withTrashed()->whereKey($f['car']->id)->value('vin'))->toBeNull()
        ->and(UserPackage::query()->whereKey($f['bought']->id)->exists())->toBeTrue();
    $car = $fleet['car']->fresh();
    expect($car->status)->toBe(FleetCar::STATUS_INVITED)->and($car->user_id)->toBeNull()->and($car->user_car_id)->toBeNull()
        ->and($car->washes_left)->toBe(2);

    // The old session is over; the same number can register again.
    $this->withToken($token)->getJson('/api/mycars')->assertUnauthorized();
    expect(makeUser($fleet['car']->driver_phone)->exists)->toBeTrue();
});

// 2 (admin side). Brands, models, companies

it('refuses deleting a brand or a model a car is on, even a removed car', function () {
    $f = auditDriver('555 73 00 16', 'LA900LA');
    $f['bought']->forceFill(['end_date' => now()->subDay()])->save();
    $f['car']->delete();
    $this->actingAs(cwAdmin());

    $this->delete(route('admin.car_brand_models_delete', [$f['brand'], $f['model']]))->assertSessionHas('error');
    $this->delete(route('admin.delete_car_brand', $f['brand']))->assertSessionHas('error');
    expect(CarModel::query()->find($f['model']->id))->not->toBeNull()
        ->and(CarBrand::query()->find($f['brand']->id))->not->toBeNull();

    $empty = CarModel::query()->forceCreate(['name' => 'Corolla', 'type' => 'sedan', 'brand_id' => $f['brand']->id]);
    $this->delete(route('admin.car_brand_models_delete', [$f['brand'], $empty]))->assertSessionHas('message');
    expect(CarModel::query()->find($empty->id))->toBeNull();
});

it('refuses deleting a company with washes, and deletes one without history', function () {
    $fleet = cwFleet(5, 1, 'LB100LB');
    $manager = cwManager('555 73 00 17');
    $washId = (int) DB::table('car_washes')->where('manager_id', $manager->id)->value('id');
    CorporateWashes::wash($fleet['car'], ['car_wash_id' => $washId, 'manager_id' => $manager->id, 'appointment_id' => null, 'walk_in' => true],
        CorporateQr::fingerprint(CorporateQr::issue($fleet['car'])));
    $this->actingAs(cwAdmin());

    $this->delete(route('admin.corporate.delete', $fleet['corp']))->assertSessionHas('error');
    expect(CorporateClient::query()->find($fleet['corp']->id))->not->toBeNull();

    $empty = CorporateClient::forceCreate(['name' => 'Empty Co', 'api_token' => CorporateClient::makeToken(), 'username' => 'emptyco',
        'password' => 'x', 'password_must_change' => false, 'session_version' => 1]);
    $this->delete(route('admin.corporate.delete', $empty))->assertSessionHas('message');
    expect(CorporateClient::query()->find($empty->id))->toBeNull();
});

// 10. Reconciliation

it('settles a paid order whose callback never came, and leaves pages still open alone', function () {
    $f = auditDriver('555 73 00 18', 'LB200LB');
    $flitt = auditFlitt();
    $make = fn (string $id, int $minutesAgo) => tap(TbcPayment::query()->create(['merchant_payment_id' => $id, 'user_id' => $f['driver']->id,
        'type' => 'package', 'amount' => 40, 'currency' => 'GEL', 'status' => 'pending',
        'payload' => ['car_id' => $f['car']->id, 'package_id' => $f['package']->id, 'price_id' => $f['price']->id, 'month' => 1, 'washes' => 4]]),
        fn ($p) => $p->forceFill(['created_at' => now()->subMinutes($minutesAgo)])->save());
    $paid = $make('PRECPAID', 40);
    $expired = $make('PRECEXP', 50);
    $open = $make('PRECOPEN', 5);
    $flitt->shouldReceive('status')->with('PRECPAID')->andReturn(['order_id' => 'PRECPAID', 'order_status' => 'approved', 'amount' => 4000]);
    $flitt->shouldReceive('status')->with('PRECEXP')->andReturn(['order_id' => 'PRECEXP', 'order_status' => 'expired', 'amount' => 4000]);
    $flitt->shouldNotReceive('status')->with('PRECOPEN');

    $this->artisan('payments:reconcile')->assertSuccessful();

    expect($paid->fresh()->status)->toBe('succeeded')
        ->and(UserPackage::query()->where('tbc_payment_id', $paid->id)->exists())->toBeTrue()
        ->and($expired->fresh()->status)->toBe('expired')
        ->and($open->fresh()->status)->toBe('pending');
});

// 9. Reports

it('shows every QR wash in the admin and manager reports and closes the booking it served', function () {
    $f = auditDriver('555 73 00 19', 'LB300LB');
    $fleet = cwFleet(5, 2, 'LB301LB');
    $manager = cwManager('555 73 00 07');
    $washId = (int) DB::table('car_washes')->where('manager_id', $manager->id)->value('id');
    $booking = Appointment::query()->create(['user_id' => $f['driver']->id, 'car_wash_id' => $washId, 'car_id' => $f['car']->id,
        'date' => now()->toDateString(), 'time' => '10:00', 'approved' => 1, 'qr_code' => Str::random(12)]);

    // A booked package wash, then a walk-in one the same day, then a company car walk-in.
    foreach (range(1, 2) as $_) {
        $qr = auditShowQr($this, $f);
        $token = $this->withToken(cwAs($manager))->getJson('/api/checkqr/'.$qr)->assertOk()->json('approve_token');
        $this->withToken(cwAs($manager))->postJson('/api/package/'.$f['bought']->id.'/approve', ['approve_token' => $token])->assertOk();
    }
    $qr = cwDriverQr($this, $fleet);
    $token = $this->withToken(cwAs($manager))->getJson('/api/checkqr/'.$qr)->assertOk()->json('approve_token');
    $this->withToken(cwAs($manager))->postJson('/api/corporate-wash/'.$fleet['car']->id.'/approve', ['approve_token' => $token])->assertOk();

    expect($booking->fresh()->approved)->toBe(3)
        ->and(PackageWash::query()->whereNotNull('appointment_id')->count())->toBe(1)
        ->and(CorporateWash::query()->count())->toBe(1);

    $this->actingAs(cwAdmin());
    $this->get(route('admin.reports.qr_washes'))->assertOk()
        ->assertSee('LB300LB')->assertSee('LB301LB')->assertSee('Wash Co')->assertSee('#'.$booking->id);
    $this->get(route('admin.reports.qr_washes', ['kind' => 'corporate']))->assertOk()->assertDontSee('LB300LB')->assertSee('LB301LB');
    $this->get(route('admin.reports.qr_washes', ['plate' => 'lb300']))->assertOk()->assertSee('LB300LB')->assertDontSee('LB301LB');
    $this->get(route('admin.reports.qr_washes', ['export' => 1]))->assertOk()->assertDownload();
    expect(QrWashReport::totals(['car_wash_id' => $washId]))->toBe(['washes' => 3, 'package' => 2, 'corporate' => 1, 'paid' => 0.0])
        ->and(QrWashReport::totals(['date_to' => now()->subDay()->toDateString()])['washes'])->toBe(0);

    $report = $this->withToken(cwAs($manager))->getJson('/api/manager/report?date_from='.now()->toDateString().'&date_to='.now()->toDateString())->assertOk();
    expect($report->json('qr_total'))->toBe(3)
        ->and(collect($report->json('qr_washes'))->pluck('kind')->sort()->values()->all())->toBe(['corporate', 'package', 'package'])
        ->and(collect($report->json('qr_washes'))->where('walk_in', true)->count())->toBe(2)
        ->and($report->json('total'))->toBe(1);
    // Another branch sees none of them.
    $other = cwManager('555 73 00 06');
    $this->withToken(cwAs($other))->getJson('/api/manager/report')->assertOk()->assertJsonPath('qr_total', 0);
});
