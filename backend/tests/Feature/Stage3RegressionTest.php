<?php

use App\Models\Appointment;
use App\Models\AppointmentsService;
use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\CarWash;
use App\Models\CarWashService;
use App\Models\Package;
use App\Models\PackagePrice;
use App\Models\TbcPayment;
use App\Models\User;
use App\Models\UserCar;
use App\Models\UserPackage;
use App\Services\BookingService;
use App\Services\Payments\FlittCheckout;
use App\Services\Payments\SettleTbcPayment;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tymon\JWTAuth\Facades\JWTAuth;

beforeEach(function () {
    Http::preventStrayRequests();
    Mail::fake();
    Queue::fake();
    $this->travelTo(now()->startOfDay()->setTime(12, 0));
});

function stage2ReviewFixture(string $suffix): array
{
    $client = makeUser('55582'.$suffix.'01');
    $manager = makeUser('55582'.$suffix.'02', User::ROLE_MANAGER);
    $wash = CarWash::create(['name' => 'Review branch', 'address' => 'Review address',
        'work_time_start' => '09:00', 'work_time_end' => '20:00', 'location' => 'Review location', 'manager_id' => $manager->id]);
    $brand = CarBrand::create(['name' => 'Review brand']);
    $model = CarModel::create(['name' => 'Review model', 'brand_id' => $brand->id, 'type' => 'sedan']);
    $car = UserCar::create(['car_type' => 'sedan', 'body_status' => 'confirmed', 'user_id' => $client->id, 'model_id' => $model->id, 'plate' => 'AA123BB']);
    $service = CarWashService::forceCreate(['name' => 'Review service']);
    $wash->services()->attach($service->id);

    return compact('client', 'manager', 'wash', 'brand', 'model', 'car', 'service');
}

function stage2ReviewBooking(array $f, array $extra = []): array
{
    return $extra + ['car_wash_id' => $f['wash']->id, 'car_id' => $f['car']->id,
        'service_id' => $f['service']->id, 'date' => now()->addDay()->toDateString(), 'time' => '10:00'];
}

it('returns a real car id when adding a local catalog selection', function () {
    $f = stage2ReviewFixture('01');
    $this->withToken(JWTAuth::fromUser($f['client']))->postJson('/api/mycars/add', [
        'source' => 'local', 'brand_id' => $f['brand']->id, 'model_id' => $f['model']->id, 'plate' => 'AA124BB', 'year' => 2020,
    ])->assertOk()->assertJsonPath('success', true)->assertJsonPath('car.year', 2020)->assertJsonPath('car.plate', 'AA124BB');
});

it('refuses booking a car owned by another customer', function () {
    $f = stage2ReviewFixture('02');
    $other = makeUser('555820299');
    $foreign = UserCar::create(['car_type' => 'sedan', 'body_status' => 'confirmed', 'user_id' => $other->id, 'model_id' => $f['model']->id, 'plate' => 'CC123DD']);
    $this->withToken(JWTAuth::fromUser($f['client']))->postJson('/api/appointments/add', stage2ReviewBooking($f, ['car_id' => $foreign->id]))->assertNotFound();
    expect(Appointment::where('user_id', $f['client']->id)->where('car_id', $foreign->id)->count())->toBe(0);
});

it('refuses a service that the selected branch does not offer', function () {
    $f = stage2ReviewFixture('03');
    $unavailable = CarWashService::forceCreate(['name' => 'Service not at this branch']);
    $this->withToken(JWTAuth::fromUser($f['client']))->postJson('/api/appointments/add', stage2ReviewBooking($f, ['service_id' => $unavailable->id]))->assertStatus(422);
    expect(Appointment::where('user_id', $f['client']->id)->count())->toBe(0);
});

it('refuses a booking outside the branch working hours', function () {
    $f = stage2ReviewFixture('04');
    $this->withToken(JWTAuth::fromUser($f['client']))->postJson('/api/appointments/add', stage2ReviewBooking($f, ['time' => '03:00']))->assertStatus(422);
    expect(Appointment::where('user_id', $f['client']->id)->count())->toBe(0);
});

it('does not leave a booking when writing its service fails', function () {
    $f = stage2ReviewFixture('05');
    AppointmentsService::creating(fn () => throw new RuntimeException('Review injected service write failure'));
    $this->withToken(JWTAuth::fromUser($f['client']))->postJson('/api/appointments/add', stage2ReviewBooking($f))->assertStatus(500);
    expect(Appointment::where('user_id', $f['client']->id)->count())->toBe(0);
});

it('loads a car from the endpoint used by EditCar after a page refresh', function () {
    $f = stage2ReviewFixture('06');
    $this->withToken(JWTAuth::fromUser($f['client']))->getJson('/api/mycars/'.$f['car']->id)->assertOk();
});

it('accepts a valid booking fixture with an owned car and offered service during working hours', function () {
    $f = stage2ReviewFixture('07');
    $this->withToken(JWTAuth::fromUser($f['client']))->postJson('/api/appointments/add', stage2ReviewBooking($f))->assertOk()->assertJsonPath('success', true);
    expect(Appointment::where('user_id', $f['client']->id)->count())->toBe(1);
});

function stage2ReviewPackage(array $f): array
{
    $package = Package::create(['car_type' => 'sedan', 'count_washes' => 5]);
    $price = PackagePrice::create(['package_id' => $package->id, 'month' => 1, 'price' => 20]);
    $checkout = Mockery::mock(FlittCheckout::class);
    $checkout->shouldReceive('checkoutUrl')->andReturn('https://checkout.example.test/review-only');
    app()->instance(FlittCheckout::class, $checkout);

    return compact('package', 'price');
}

it('lets a customer buy a replacement after the previous package expired and was exhausted', function () {
    $f = stage2ReviewFixture('08');
    $p = stage2ReviewPackage($f);
    UserPackage::create([
        'user_id' => $f['client']->id, 'user_car_id' => $f['car']->id,
        'package_id' => $p['package']->id, 'price_id' => $p['price']->id,
        'start_date' => now()->subMonths(2), 'end_date' => now()->subMonth(),
        'number_of_washes' => 5, 'used_washes' => 5, 'qr_code' => 'REVIEWEXPIRED001',
    ]);
    $this->withToken(JWTAuth::fromUser($f['client']))->postJson('/api/packages/buy', [
        'car_id' => $f['car']->id, 'number_of_washes' => 5, 'renewal' => false, 'sub_term' => 1,
    ])->assertOk()->assertJsonPath('success', true);
});

it('does not mark two separately paid package orders successful while granting only one benefit', function () {
    $f = stage2ReviewFixture('09');
    $p = stage2ReviewPackage($f);
    $token = JWTAuth::fromUser($f['client']);
    $payload = ['car_id' => $f['car']->id, 'number_of_washes' => 5, 'renewal' => false, 'sub_term' => 1];
    $this->withToken($token)->postJson('/api/packages/buy', $payload)->assertOk()->assertJsonPath('success', true);
    $this->withToken($token)->postJson('/api/packages/buy', $payload);
    $orders = TbcPayment::where('user_id', $f['client']->id)->get();
    expect($orders)->not->toBeEmpty();
    $settle = app(SettleTbcPayment::class);
    foreach ($orders as $order) {
        $settle->grantNow($order);
    }
    $paidOrders = TbcPayment::where('user_id', $f['client']->id)->where('status', 'succeeded')->count();
    expect($paidOrders)->toBeGreaterThanOrEqual(1);
    expect(UserPackage::where('user_id', $f['client']->id)->sum('number_of_washes'))->toBeGreaterThanOrEqual(5 * $paidOrders);
});

it('revokes an existing customer JWT when an administrator resets the customer password', function () {
    $client = makeUser('555821001');
    $client->forceFill(['name' => 'Review', 'surname' => 'Customer', 'email' => 'password-review@example.test', 'sex' => 'male'])->save();
    $token = JWTAuth::fromUser($client);
    $admin = makeUser('555821002', User::ROLE_ADMIN);
    $this->actingAs($admin, 'web')->post('/dashboard/clients/'.$client->id.'/edit_save', [
        'name' => $client->name, 'surname' => $client->surname, 'email' => $client->email,
        'phone' => $client->phone, 'sex' => 'male', 'password' => 'new-review-password',
    ])->assertRedirect();
    expect(Hash::check('new-review-password', $client->fresh()->password))->toBeTrue();
    foreach (['tymon.jwt', 'tymon.jwt.auth'] as $id) {
        app()->forgetInstance($id);
    }
    JWTAuth::clearResolvedInstances();
    app('auth')->forgetGuards();
    $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
});

it('grants two already paid independent orders and never grants one order twice', function () {
    $f = stage2ReviewFixture('11');
    $p = stage2ReviewPackage($f);
    $orders = [];
    foreach (['REVIEWPAID001', 'REVIEWPAID002'] as $merchant) {
        $orders[] = TbcPayment::create([
            'merchant_payment_id' => $merchant, 'user_id' => $f['client']->id,
            'type' => 'package', 'amount' => 20, 'currency' => 'GEL', 'status' => 'pending',
            'payload' => ['car_id' => $f['car']->id, 'package_id' => $p['package']->id, 'price_id' => $p['price']->id, 'month' => 1, 'washes' => 5],
        ]);
    }
    $settle = app(SettleTbcPayment::class);
    foreach ($orders as $order) {
        $settle->grantNow($order);
    }
    $settle->grantNow($orders[0]);
    expect(UserPackage::where('user_id', $f['client']->id)->count())->toBe(2);
    expect(UserPackage::where('user_id', $f['client']->id)->sum('number_of_washes'))->toBe(10);
    expect(UserPackage::where('tbc_payment_id', $orders[0]->id)->count())->toBe(1);
});

it('reschedules with a distinct status and preserves cancellation history', function () {
    $f = stage2ReviewFixture('12');
    $booking = app(BookingService::class)->create($f['client']->id, stage2ReviewBooking($f) + ['services' => [$f['service']->id]]);
    $token = JWTAuth::fromUser($f['manager']);
    $this->withToken($token)->postJson('/api/appointments/'.$booking->id.'/edit', ['date' => now()->addDays(2)->toDateString(), 'time' => '11:00', 'status' => 5])
        ->assertOk()->assertJsonPath('status', 5);
    expect((int) $booking->fresh()->approved)->toBe(5);
    $this->withToken($token)->postJson('/api/appointments/'.$booking->id.'/edit', ['status' => 2])->assertOk();
    expect((int) $booking->fresh()->approved)->toBe(2);
});

it('treats completion as completion without moving the booking', function () {
    $f = stage2ReviewFixture('13');
    $booking = app(BookingService::class)->create($f['client']->id, stage2ReviewBooking($f) + ['services' => [$f['service']->id]]);
    $this->withToken(JWTAuth::fromUser($f['manager']))->postJson('/api/appointments/'.$booking->id.'/edit', ['status' => 3])
        ->assertOk()->assertJsonPath('status', 3);
    expect((int) $booking->fresh()->approved)->toBe(3);
    expect($booking->fresh()->date)->toBe(now()->addDay()->toDateString());
});

it('rejects a duplicate occupied slot but permits it after cancellation', function () {
    $f = stage2ReviewFixture('14');
    $booking = app(BookingService::class)->create($f['client']->id, stage2ReviewBooking($f) + ['services' => [$f['service']->id]]);
    $this->withToken(JWTAuth::fromUser($f['client']))->postJson('/api/appointments/add', stage2ReviewBooking($f))->assertStatus(422);
    $booking->update(['approved' => 2]);
    $this->withToken(JWTAuth::fromUser($f['client']))->postJson('/api/appointments/add', stage2ReviewBooking($f))->assertOk();
});

it('lists published generation years and rejects an incompatible production year', function () {
    $f = stage2ReviewFixture('15');
    config(['services.carapi.url' => 'https://catalog.example.test/api/v1', 'services.carapi.secret' => 'review-catalog-key']);
    Http::fake([
        'https://catalog.example.test/api/v1/brands?*' => Http::response(['data' => [['id' => 901, 'name' => 'Remote brand', 'slug' => 'remote-brand']], 'meta' => ['total' => 1]]),
        'https://catalog.example.test/api/v1/brands/remote-brand/models?*' => Http::response(['data' => [['id' => 7, 'name' => 'Remote model']], 'meta' => ['total' => 1]]),
        'https://catalog.example.test/api/v1/models/7/generations?*' => Http::response(['data' => [['start_year' => 2018, 'end_year' => 2020]], 'meta' => ['total' => 1]]),
    ]);
    $token = JWTAuth::fromUser($f['client']);
    $this->withToken($token)->getJson('/api/cars/years?source=carapi&brand_id=901&model_id=7')
        ->assertOk()->assertJsonPath('source', 'catalog')->assertJsonPath('years', [2020, 2019, 2018]);
    $this->withToken($token)->postJson('/api/mycars/add', ['source' => 'carapi', 'brand_id' => 901, 'model_id' => 7, 'plate' => 'ZZ123ZZ', 'year' => 2000])->assertStatus(422);
});

it('labels missing generation coverage as manual year selection', function () {
    $f = stage2ReviewFixture('16');
    $this->withToken(JWTAuth::fromUser($f['client']))->getJson('/api/cars/years?source=local&brand_id='.$f['brand']->id.'&model_id='.$f['model']->id)
        ->assertOk()->assertJsonPath('source', 'manual');
});

it('keeps expired package history and does not repeat the expiry notification', function () {
    $f = stage2ReviewFixture('17');
    $p = stage2ReviewPackage($f);
    $package = UserPackage::create([
        'user_id' => $f['client']->id, 'user_car_id' => $f['car']->id, 'package_id' => $p['package']->id,
        'price_id' => $p['price']->id, 'end_date' => now()->subDay(), 'number_of_washes' => 5, 'used_washes' => 5, 'qr_code' => 'HISTORYONLY001',
    ]);
    $this->artisan('user-packages:check')->assertExitCode(0);
    expect($package->fresh())->not->toBeNull();
    $notifiedAt = $package->fresh()->expiration_notified_at;
    expect($notifiedAt)->not->toBeNull();
    $this->travel(1)->minutes();
    $this->artisan('user-packages:check')->assertExitCode(0);
    expect($package->fresh()->expiration_notified_at)->toBe($notifiedAt);
});

it('rejects the same vehicle booked at the same time in another branch', function () {
    $f = stage2ReviewFixture('18');
    $otherManager = makeUser('555821899', User::ROLE_MANAGER);
    $otherWash = CarWash::create(['name' => 'Other review branch', 'address' => 'Review',
        'work_time_start' => '09:00', 'work_time_end' => '20:00', 'location' => 'Review', 'manager_id' => $otherManager->id]);
    $otherWash->services()->attach($f['service']->id);
    app(BookingService::class)->create($f['client']->id, stage2ReviewBooking($f) + ['services' => [$f['service']->id]]);
    $this->withToken(JWTAuth::fromUser($f['client']))->postJson('/api/appointments/add',
        stage2ReviewBooking($f, ['car_wash_id' => $otherWash->id]))->assertStatus(422);
    expect(Appointment::count())->toBe(1);
});

it('localizes a closed-branch error in the requested API language', function () {
    $f = stage2ReviewFixture('19');
    $this->withToken(JWTAuth::fromUser($f['client']))->withHeader('Accept-Language', 'en')
        ->postJson('/api/appointments/add', stage2ReviewBooking($f, ['time' => '03:00']))
        ->assertStatus(422)->assertJsonPath('error', 'The branch is closed at the selected time.');
});

it('does not expose another customer car on the single car endpoint', function () {
    $f = stage2ReviewFixture('20');
    $other = makeUser('555822099');
    $this->withToken(JWTAuth::fromUser($other))->getJson('/api/mycars/'.$f['car']->id)->assertNotFound();
});
