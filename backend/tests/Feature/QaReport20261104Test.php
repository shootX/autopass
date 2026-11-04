<?php

// External QA report (2026-11-04): F1-F5 and QA05 (owner chose: the company's driver phone follows the account).

use App\Console\Commands\ReconcilePayments;
use App\Enums\AppointmentStatus;
use App\Jobs\SendFleetInvite;
use App\Jobs\SendPush;
use App\Models\FleetCar;
use App\Models\UserCar;
use App\Models\PackagePrice;
use App\Models\Package;
use App\Models\CarModel;
use App\Models\CarBrand;
use App\Models\PromoCode;
use App\Models\TbcPayment;
use App\Models\User;
use App\Models\UserPackage;
use App\Models\UserPushToken;
use App\Services\FleetInvites;
use App\Services\Payments\FlittCheckout;
use App\Services\Payments\SettleTbcPayment;
use App\Services\Sms\MockSmsGateway;
use App\Support\AppSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

function qa04PushDevice(User $user): void
{
    UserPushToken::query()->create(['user_id' => $user->id, 'push_id' => 'apns-'.$user->id, 'kind' => UserPushToken::KIND_APNS]);
}

/** A driver with a confirmed sedan and a 3-wash 50% discount package (as the discount package tests set up). */
function qa04Discount(string $phone): array
{
    $brand = CarBrand::query()->firstOrCreate(['name' => 'Toyota']);
    $model = CarModel::query()->forceCreate(['name' => 'Camry', 'type' => 'sedan', 'brand_id' => $brand->id]);
    $driver = makeUser($phone);
    $car = UserCar::query()->create(['user_id' => $driver->id, 'model_id' => $model->id, 'plate' => 'DP100DP',
        'car_type' => 'sedan', 'body_status' => 'confirmed']);
    $limited = Package::query()->create(['car_type' => 'sedan', 'count_washes' => 3, 'type' => 'discount', 'full_price' => 30, 'percent' => 50]);
    PackagePrice::query()->create(['package_id' => $limited->id, 'month' => 1, 'price' => 10]);
    $checkout = Mockery::mock(FlittCheckout::class);
    $checkout->shouldReceive('checkoutUrl')->andReturn('https://checkout.example.test/discount');
    app()->instance(FlittCheckout::class, $checkout);

    return compact('driver', 'car', 'limited', 'model');
}

function qa04Bought(array $f): UserPackage
{
    test()->withToken(cwAs($f['driver']))->postJson('/api/packages/buy', ['car_id' => $f['car']->id, 'package_id' => $f['limited']->id, 'renewal' => false])
        ->assertOk();
    $payment = TbcPayment::query()->latest('id')->firstOrFail();
    app(SettleTbcPayment::class)->grantNow($payment);

    return UserPackage::query()->where('tbc_payment_id', $payment->id)->firstOrFail();
}

/** The queue can't take any job from here on, as when its table or server is down. */
function qa04BreakQueue(): void
{
    config(['queue.connections.broken' => ['driver' => 'qa-missing-driver'], 'queue.default' => 'broken']);
}

// F1: a push the queue can't take never turns a saved wash into an error answer.
it('answers success for a package wash even when the push queue fails', function () {
    $f = qa04Discount('555 73 00 01');
    $package = qa04Bought($f);
    qa04PushDevice($f['driver']);
    $manager = cwManager('555 73 00 02');
    $token = $this->withToken(cwAs($manager))->getJson('/api/checkqr/'.freshPackageQr($package->id))->assertOk()->json('approve_token');
    qa04BreakQueue();

    $this->withToken(cwAs($manager))->postJson("/api/package/{$package->id}/approve", ['approve_token' => $token])
        ->assertOk()->assertJsonPath('success', true);
    expect((int) UserPackage::query()->find($package->id)->used_washes)->toBe(1);
});

it('answers success for a company wash even when the push queue fails', function () {
    $fleet = cwFleet(10, 3);
    qa04PushDevice($fleet['driver']);
    $manager = cwManager('555 73 00 03');
    $qr = cwDriverQr($this, $fleet);
    $token = $this->withToken(cwAs($manager))->getJson('/api/checkqr/'.$qr)->assertOk()->json('approve_token');
    qa04BreakQueue();

    $this->withToken(cwAs($manager))->postJson('/api/corporate-wash/'.$fleet['car']->id.'/approve', ['approve_token' => $token])
        ->assertOk()->assertJsonPath('corporate.washes_left', 2);
});

// F2: a payment page that was never made doesn't hold the promo code.
it('lets go of the promo code at once when the payment page could not be made', function () {
    AppSettings::store(['point_price' => 1, 'point_discount' => 0]);
    PromoCode::query()->create(['code' => 'ONEUSE', 'kind' => 'discount', 'value' => 50, 'max_uses' => 1]);
    $flitt = Mockery::mock(FlittCheckout::class);
    $flitt->shouldReceive('checkoutUrl')->once()->andThrow(new RuntimeException('Flitt is down'));
    $flitt->shouldReceive('checkoutUrl')->andReturn('https://pay.example/ok');
    app()->instance(FlittCheckout::class, $flitt);
    $first = makeUser('555 73 00 11');
    $other = makeUser('555 73 00 12');

    $this->withToken(cwAs($first))->postJson('/api/points/buy', ['amount' => 50, 'promo_code' => 'ONEUSE'])
        ->assertStatus(503)->assertJsonPath('code', 'checkout_failed');
    expect(TbcPayment::query()->value('status'))->toBe(TbcPayment::STATUS_UNOPENED);

    // The single use is free again: another driver can take it.
    $this->withToken(cwAs($other))->postJson('/api/points/buy', ['amount' => 50, 'promo_code' => 'ONEUSE'])
        ->assertOk()->assertJsonPath('url', 'https://pay.example/ok');
});

it('closes old pending payments that never got a page once Flitt says it has no order', function () {
    $user = makeUser('555 73 00 13');
    PromoCode::query()->create(['code' => 'STUCK', 'kind' => 'discount', 'value' => 50, 'max_uses' => 1]);
    $promo = PromoCode::query()->where('code', 'STUCK')->firstOrFail();
    $stuck = TbcPayment::query()->create([
        'merchant_payment_id' => 'PSTUCK0000000001', 'user_id' => $user->id, 'type' => 'points', 'amount' => 25,
        'currency' => 'GEL', 'status' => 'pending', 'payload' => ['points' => 50, 'promo_code_id' => $promo->id],
    ]);
    $opened = TbcPayment::query()->create([
        'merchant_payment_id' => 'POPEN00000000001', 'user_id' => $user->id, 'type' => 'points', 'amount' => 25,
        'currency' => 'GEL', 'status' => 'pending', 'payload' => ['points' => 50, 'checkout_url' => 'https://pay.example/x'],
    ]);
    $flitt = Mockery::mock(FlittCheckout::class);
    // Flitt has no order for the page it never made; an answer without a status for an opened page proves nothing.
    $flitt->shouldReceive('status')->with('PSTUCK0000000001')->andThrow(new Flitt\Exception\ApiException('Order not found'));
    $flitt->shouldReceive('status')->with('POPEN00000000001')->andReturn([]);
    app()->instance(FlittCheckout::class, $flitt);
    $this->travel(40)->minutes();

    $this->artisan(ReconcilePayments::class)->assertSuccessful();
    expect($stuck->fresh()->status)->toBe(TbcPayment::STATUS_UNOPENED)
        ->and($opened->fresh()->status)->toBe('pending')
        ->and($promo->holds()->count())->toBe(0);

    // A late approval still grants what was paid.
    app(SettleTbcPayment::class)->grantNow($stuck->fresh());
    expect((int) $user->fresh()->points)->toBe(50);
});

it('lets the same driver pay again with the code after a page that was never made', function () {
    AppSettings::store(['point_price' => 1, 'point_discount' => 0]);
    $promo = PromoCode::query()->create(['code' => 'AGAIN', 'kind' => 'discount', 'value' => 50, 'max_uses' => 1]);
    $user = makeUser('555 73 00 14');
    TbcPayment::query()->create([
        'merchant_payment_id' => 'PLOST00000000001', 'user_id' => $user->id, 'type' => 'points', 'amount' => 25,
        'currency' => 'GEL', 'status' => 'pending', 'payload' => ['points' => 50, 'promo_code_id' => $promo->id],
    ]);
    $flitt = Mockery::mock(FlittCheckout::class);
    $flitt->shouldReceive('status')->with('PLOST00000000001')->andThrow(new Flitt\Exception\ApiException('Order not found'));
    $flitt->shouldReceive('checkoutUrl')->andReturn('https://pay.example/new');
    app()->instance(FlittCheckout::class, $flitt);
    $this->travel(31)->minutes();

    $this->withToken(cwAs($user))->postJson('/api/points/buy', ['amount' => 50, 'promo_code' => 'AGAIN'])
        ->assertOk()->assertJsonPath('url', 'https://pay.example/new');
});

// F3: a new driver's SMS is not lost to the resend window, and the panel says what happened.
it('sends the new driver their SMS once the resend window closes, once', function () {
    $f = cwFleet();
    app(MockSmsGateway::class)->reset();
    $f['car']->forceFill(['invited_at' => now()])->save();
    Queue::fake([SendFleetInvite::class]);

    expect(FleetInvites::setDriver($f['car'], '555 73 00 21'))->toBe('too_soon');
    expect(FleetInvites::setDriver($f['car'], '555 73 00 22'))->toBe('too_soon');
    expect(app(MockSmsGateway::class)->messages)->toHaveCount(0);
    $jobs = Queue::pushed(SendFleetInvite::class);
    expect($jobs)->toHaveCount(2);

    $this->travel(62)->seconds();
    foreach ($jobs as $job) {
        $job->handle();
    }
    // Only the phone the car has now gets an SMS, and only one.
    expect(app(MockSmsGateway::class)->messages)->toHaveCount(1)
        ->and(app(MockSmsGateway::class)->messages[0]['destination'])->toBe('995555730022');
});

it('tells the company when no SMS went out after saving the driver', function () {
    $f = cwFleet();
    $f['car']->forceFill(['invited_at' => now()])->save();
    Queue::fake([SendFleetInvite::class]);
    $back = '/partner/fleet';
    $controller = new class
    {
        use App\Http\Controllers\Concerns\FleetCarActions;

        public function run($request, $car, $back)
        {
            return $this->changeDriver($request, $car, $back);
        }
    };

    $saved = $controller->run(Illuminate\Http\Request::create('/', 'POST', ['phone' => '555 73 00 23']), $f['car'], $back);
    expect($saved->getSession()->get('message'))->toBe(__('admin.fleet_driver_saved_too_soon'));

    FleetInvites::setBlocked($f['car']->fresh(), true);
    $blocked = $controller->run(Illuminate\Http\Request::create('/', 'POST', ['phone' => '555 73 00 24']), $f['car']->fresh(), $back);
    expect($blocked->getSession()->get('errors')->first('fleet'))->toBe(__('admin.fleet_driver_saved_blocked'));
});

// F4: a page opened before the admin changed the discount is not handed out for the new terms.
it('opens a new page when the discount package terms changed', function () {
    $f = qa04Discount('555 73 00 31');
    $buy = fn () => $this->withToken(cwAs($f['driver']))
        ->postJson('/api/packages/buy', ['car_id' => $f['car']->id, 'package_id' => $f['limited']->id, 'renewal' => false])->assertOk();

    $buy();
    $buy();
    expect(TbcPayment::query()->count())->toBe(1);

    $f['limited']->update(['percent' => 80]);
    $buy();
    $payments = TbcPayment::query()->orderBy('id')->get();
    expect($payments)->toHaveCount(2)
        ->and((int) $payments[1]->payload['percent'])->toBe(80);

    app(SettleTbcPayment::class)->grantNow($payments[1]);
    expect(UserPackage::query()->where('tbc_payment_id', $payments[1]->id)->value('discount_percent'))->toBe(80);
});

// F5: the push switches in the app are kept.
it('skips booking and renewal pushes the user switched off, and still sends the others', function () {
    $user = makeUser('555 73 00 41');
    qa04PushDevice($user);
    $user->forceFill(['enable_push_wash_appointment' => false, 'enable_push_renewal_subscription' => false])->save();
    Queue::fake([SendPush::class]);

    $user->fresh()->sendPush(['en' => 'Booking'], ['en' => 'b'], setting: User::PUSH_BOOKINGS);
    $user->fresh()->sendPush(['en' => 'Renewal'], ['en' => 'r'], setting: User::PUSH_RENEWALS);
    Queue::assertNothingPushed();

    $user->fresh()->sendPush(['en' => 'The wash has begun'], ['en' => 'w']);
    Queue::assertPushed(SendPush::class, 1);

    $user->forceFill(['enable_push_wash_appointment' => true])->save();
    $user->fresh()->sendPush(['en' => 'Booking'], ['en' => 'b'], setting: User::PUSH_BOOKINGS);
    Queue::assertPushed(SendPush::class, 2);
});

it('does not push a booking confirmation to a user who switched booking pushes off', function () {
    $manager = makeUser('555 73 00 42', User::ROLE_MANAGER);
    $client = makeUser('555 73 00 43');
    qa04PushDevice($client);
    $client->forceFill(['enable_push_wash_appointment' => false])->save();
    $now = ['created_at' => now(), 'updated_at' => now()];
    $wash = DB::table('car_washes')->insertGetId(['name' => 'W', 'address' => 'a', 'work_time_start' => '09:00', 'work_time_end' => '20:00',
        'location' => 'x', 'manager_id' => $manager->id] + $now);
    $brand = CarBrand::query()->firstOrCreate(['name' => 'Toyota']);
    $model = CarModel::query()->forceCreate(['name' => 'Camry', 'type' => 'sedan', 'brand_id' => $brand->id]);
    $car = DB::table('user_cars')->insertGetId(['car_type' => 'sedan', 'body_status' => 'confirmed', 'user_id' => $client->id, 'model_id' => $model->id, 'plate' => 'QA44QA'] + $now);
    $id = DB::table('appointments')->insertGetId(['user_id' => $client->id, 'car_wash_id' => $wash, 'car_id' => $car,
        'date' => now()->addDay()->toDateString(), 'time' => '10:00', 'qr_code' => 'QA05BOOK1', 'approved' => 0] + $now);
    Queue::fake([SendPush::class]);

    $this->withToken(cwAs($manager))->postJson('/api/appointments/'.$id.'/edit', ['status' => AppointmentStatus::Confirmed->value])
        ->assertOk()->assertJsonPath('status', AppointmentStatus::Confirmed->value);
    Queue::assertNotPushed(SendPush::class);
});

// QA05: the company sees the driver's new phone, and the car stays theirs.
it('moves the company driver phone along when the driver changes their account phone', function () {
    $f = cwFleet();
    $other = cwFleet(0, 0, 'BB456CC', $f['corp']);
    $old = $f['driver']->phone;
    $invitedOnly = FleetCar::query()->create(['corporate_client_id' => $f['corp']->id, 'plate' => 'CC789DD', 'brand' => 'Toyota', 'model' => 'Camry', 'source' => 'manual']);
    $invitedOnly->forceFill(['status' => FleetCar::STATUS_INVITED, 'driver_phone' => $f['car']->driver_phone])->save();

    $f['driver']->forceFill(['phone' => '555730051'])->save();
    expect(FleetInvites::phoneChanged($f['driver'], $old))->toBe(1);

    $car = $f['car']->fresh();
    expect($car->driver_phone)->toBe('555 73 00 51')
        ->and($car->status)->toBe(FleetCar::STATUS_ACTIVE)
        ->and((int) $car->user_id)->toBe((int) $f['driver']->id)
        ->and($other['car']->fresh()->driver_phone)->toBe($other['car']->driver_phone)
        ->and($invitedOnly->fresh()->driver_phone)->toBe($f['car']->driver_phone);

    // Saving that phone in the panel now is the same driver: the car is not handed over.
    expect(FleetInvites::setDriver($car, '555 73 00 51'))->not->toBe('phone_invalid');
    expect((int) $car->fresh()->user_car_id)->toBe((int) $f['userCar']->id);
});
