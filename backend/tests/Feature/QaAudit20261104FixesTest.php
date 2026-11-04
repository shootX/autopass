<?php

// Strict QA audit 2026-11-04 (QA-01..QA-13) and the owner's answers: renewal switch covers "package ended",
// cancel up to an hour before, one active booking per car a week, the manager sees the phone on bookings only,
// pushes go to every signed-in phone.

use App\Enums\AppointmentStatus;
use App\Jobs\SendPush;
use App\Models\Appointment;
use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\CarWash;
use App\Models\CarWashService;
use App\Models\CorporateClient;
use App\Models\CorporateInvoice;
use App\Models\CorporatePackage;
use App\Models\CorporateWashPool;
use App\Models\Package;
use App\Models\PackagePrice;
use App\Models\TbcPayment;
use App\Models\User;
use App\Models\UserCar;
use App\Models\UserPackage;
use App\Models\UserPushToken;
use App\Services\CorporateInvoices;
use App\Services\Payments\FlittCheckout;
use App\Services\Push\ApnsClient;
use App\Services\PushService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

function qa11Booking(string $suffix): array
{
    $client = makeUser('55591'.$suffix.'01');
    $manager = makeUser('55591'.$suffix.'02', User::ROLE_MANAGER);
    $wash = CarWash::create(['name' => 'QA branch', 'address' => 'QA address',
        'work_time_start' => '09:00', 'work_time_end' => '20:00', 'location' => 'QA', 'manager_id' => $manager->id]);
    $brand = CarBrand::create(['name' => 'QA brand '.$suffix]);
    $model = CarModel::create(['name' => 'QA model', 'brand_id' => $brand->id, 'type' => 'sedan']);
    $car = UserCar::create(['car_type' => 'sedan', 'body_status' => 'confirmed', 'user_id' => $client->id, 'model_id' => $model->id, 'plate' => 'QA'.$suffix.'1AA']);
    $service = CarWashService::forceCreate(['name' => 'QA service']);
    $wash->services()->attach($service->id);

    return compact('client', 'manager', 'wash', 'car', 'service', 'model');
}

function qa11Book($test, array $f, string $date, string $time = '10:00', ?UserCar $car = null)
{
    return $test->withToken(cwAs($f['client']))->postJson('/api/appointments/add', ['car_wash_id' => $f['wash']->id,
        'car_id' => ($car ?? $f['car'])->id, 'service_id' => $f['service']->id, 'date' => $date, 'time' => $time]);
}

function qa11Corp(): CorporateClient
{
    return CorporateClient::forceCreate([
        'name' => 'Inv Co', 'api_token' => CorporateClient::makeToken(), 'username' => 'invco',
        'password' => Hash::make('corppass1'), 'password_must_change' => false, 'session_version' => 1, 'washes_balance' => 0,
    ]);
}

function qa11WashPackage(CorporateClient $corp, string $type = 'sedan'): CorporatePackage
{
    $package = new CorporatePackage(['title' => '100 washes', 'washes' => 100, 'price' => 1500, 'is_active' => true]);
    $package->forceFill(['corporate_client_id' => $corp->id, 'type' => 'washes', 'car_type' => $type])->save();

    return $package;
}

function qa11Device(User $user, string $hex): void
{
    UserPushToken::query()->create(['user_id' => $user->id, 'push_id' => str_repeat($hex, 32), 'kind' => UserPushToken::KIND_APNS]);
}

beforeEach(function () {
    Http::preventStrayRequests();
    // Wednesday noon: the week runs Monday 2026-11-02 to Sunday 2026-11-08.
    $this->travelTo(now()->setDate(2026, 11, 4)->setTime(12, 0));
});

// QA-01
it('pays an invoice whose package was deleted, crediting the type on the invoice', function () {
    $corp = qa11Corp();
    $package = qa11WashPackage($corp);
    $invoice = CorporateInvoices::issue($corp, $package, 'company');
    expect($invoice->car_type)->toBe('sedan');

    $this->actingAs(cwAdmin())->delete(route('admin.packages.corporate.delete', $package))->assertRedirect();

    expect(CorporateInvoices::markPaid($invoice->fresh(), cwAdmin()->id))->toBe('ok')
        ->and($invoice->fresh()->status)->toBe(CorporateInvoice::STATUS_PAID)
        ->and((int) $corp->fresh()->washes_balance)->toBe(100)
        ->and(CorporateWashPool::query()->where('corporate_client_id', $corp->id)->pluck('balance', 'car_type')->all())->toBe(['sedan' => 100]);
});

// QA-02
it('credits the body type the invoice was issued for, not the package\'s edited one', function () {
    $corp = qa11Corp();
    $package = qa11WashPackage($corp, 'sedan');
    $invoice = CorporateInvoices::issue($corp, $package, 'company');
    $package->forceFill(['car_type' => 'suv'])->save();

    expect(CorporateInvoices::markPaid($invoice->fresh(), cwAdmin()->id))->toBe('ok')
        ->and(CorporateWashPool::query()->where('corporate_client_id', $corp->id)->pluck('balance', 'car_type')->all())->toBe(['sedan' => 100]);
});

// QA-03
it('sends the manager a 15-minute reminder in three languages, only for bookings still on', function () {
    Queue::fake();
    $f = qa11Booking('03');
    qa11Device($f['manager'], 'ab');
    $at = now()->addMinutes(15);
    Appointment::query()->create(['user_id' => $f['client']->id, 'car_wash_id' => $f['wash']->id, 'car_id' => $f['car']->id,
        'date' => $at->toDateString(), 'time' => $at->format('H:i:00'), 'approved' => AppointmentStatus::Confirmed->value, 'qr_code' => 'a1']);
    Appointment::query()->create(['user_id' => $f['client']->id, 'car_wash_id' => $f['wash']->id, 'car_id' => $f['car']->id,
        'date' => $at->toDateString(), 'time' => $at->format('H:i:00'), 'approved' => AppointmentStatus::Cancelled->value, 'qr_code' => 'a2']);

    $this->artisan('app:send-manager-fifteen-minutes-before')->assertSuccessful();
    Queue::assertPushed(SendPush::class, 1);
});

it('sends a push given as plain text instead of failing', function () {
    $user = makeUser('555 91 03 09');
    qa11Device($user, 'ab');
    $sent = [];
    $apns = Mockery::mock(ApnsClient::class);
    $apns->shouldReceive('send')->andReturnUsing(function ($token, $title, $body) use (&$sent) {
        $sent[] = [$title, $body];

        return 'sent';
    });
    app()->instance(ApnsClient::class, $apns);

    (new PushService(str_repeat('ab', 32), 'Title', 'Body'))->send();
    expect($sent)->toBe([['Title', 'Body']]);
});

// QA-04
it('cancels the bookings ahead when a branch is deleted and tells the drivers', function () {
    Queue::fake();
    $f = qa11Booking('04');
    $id = qa11Book($this, $f, now()->addDay()->toDateString())->assertOk()->json('appointment.id');
    qa11Device($f['client'], 'cd');

    $this->actingAs(cwAdmin())->delete(route('admin.washings.delete', $f['wash']))->assertRedirect();

    expect((int) Appointment::query()->find($id)->approved)->toBe(AppointmentStatus::Cancelled->value);
    Queue::assertPushed(SendPush::class, 1);
});

// QA-05 and the owner's answer 3
it('allows one active booking per car a week, and again the next week or after cancelling', function () {
    Queue::fake();
    $f = qa11Booking('05');
    qa11Book($this, $f, '2026-11-05')->assertOk();
    qa11Book($this, $f, '2026-11-07', '11:00')->assertStatus(422)->assertJsonPath('error', __('booking.week_limit', [], 'en'));
    // Next week is a new week; another car of the same driver has its own.
    qa11Book($this, $f, '2026-11-09')->assertOk();
    $second = UserCar::create(['car_type' => 'sedan', 'body_status' => 'confirmed', 'user_id' => $f['client']->id, 'model_id' => $f['model']->id, 'plate' => 'QA052BB']);
    qa11Book($this, $f, '2026-11-06', '12:00', $second)->assertOk();

    $first = Appointment::query()->where('car_id', $f['car']->id)->whereDate('date', '2026-11-05')->firstOrFail();
    $this->withToken(cwAs($f['client']))->deleteJson("/api/appointments/{$first->id}/remove")->assertOk();
    qa11Book($this, $f, '2026-11-07', '11:00')->assertOk();
});

// The owner's answer 2: cancelling follows the usual rule, an hour before at the latest, and stays in the history.
it('keeps a cancelled booking as cancelled and refuses cancelling within the last hour', function () {
    Queue::fake();
    $f = qa11Booking('06');
    $id = qa11Book($this, $f, now()->toDateString(), '15:00')->assertOk()->json('appointment.id');
    $this->withToken(cwAs($f['client']))->deleteJson("/api/appointments/{$id}/remove")->assertOk()->assertJsonPath('status', AppointmentStatus::Cancelled->value);
    expect((int) Appointment::query()->find($id)->approved)->toBe(AppointmentStatus::Cancelled->value);
    // Cancelled once is final.
    $this->withToken(cwAs($f['client']))->deleteJson("/api/appointments/{$id}/remove")->assertStatus(422);

    $late = qa11Book($this, $f, now()->toDateString(), '12:30')->assertOk()->json('appointment.id');
    $this->withToken(cwAs($f['client']))->deleteJson("/api/appointments/{$late}/remove")->assertStatus(422);
    expect((int) Appointment::query()->find($late)->approved)->toBe(AppointmentStatus::Pending->value);

    // Someone else's booking is not found.
    $other = makeUser('555 91 06 99');
    $this->withToken(cwAs($other))->deleteJson("/api/appointments/{$late}/remove")->assertNotFound();
});

// QA-06 and the owner's answer 4
it('shows the manager only the name and phone of a booked client', function () {
    Queue::fake();
    $f = qa11Booking('07');
    $f['client']->forceFill(['name' => 'Nino', 'email' => 'client@example.test', 'points' => 777, 'date_of_birth' => '1990-01-01'])->save();
    qa11Book($this, $f, now()->addDay()->toDateString())->assertOk();

    $user = $this->withToken(cwAs($f['manager']))->getJson('/api/allrecords')->assertOk()->json('appointments.0.user');
    expect(array_keys($user))->toEqualCanonicalizing(['id', 'name', 'surname', 'phone'])
        ->and($user['name'])->toBe('Nino');
});

// QA-07, QA-11 and the owner's answer 5
it('pushes to every phone the account is signed in on and drops only the phone that signs out', function () {
    Queue::fake();
    $user = makeUser('555 91 08 01');
    $token = cwAs($user);
    $this->withToken($token)->postJson('/api/device/apns', ['token' => str_repeat('a1', 32)])->assertOk();
    $this->withToken($token)->postJson('/api/device/apns', ['token' => str_repeat('b2', 32)])->assertOk();
    expect(UserPushToken::query()->where('user_id', $user->id)->count())->toBe(2);

    $user->sendPush(['ka' => 't'], ['ka' => 'b']);
    Queue::assertPushed(SendPush::class, function (SendPush $job) {
        $device = (new ReflectionProperty($job, 'device'))->getValue($job);

        return is_array($device) && count($device) === 2;
    });

    $this->withToken($token)->postJson('/api/logout', ['apns_token' => str_repeat('a1', 32)])->assertOk();
    expect(UserPushToken::query()->where('user_id', $user->id)->pluck('push_id')->all())->toBe([str_repeat('b2', 32)]);
});

it('moves a phone to the account signed in on it now', function () {
    $first = makeUser('555 91 08 02');
    $second = makeUser('555 91 08 03');
    $this->withToken(cwAs($first))->postJson('/api/device/apns', ['token' => str_repeat('c3', 32)])->assertOk();
    $this->withToken(cwAs($second))->postJson('/api/device/apns', ['token' => str_repeat('c3', 32)])->assertOk();

    expect(UserPushToken::query()->where('push_id', str_repeat('c3', 32))->pluck('user_id')->all())->toBe([$second->id]);
});

it('keeps at most ten phones per account', function () {
    $user = makeUser('555 91 08 04');
    $token = cwAs($user);
    foreach (range(1, 11) as $i) {
        UserPushToken::query()->create(['user_id' => $user->id, 'push_id' => str_pad(dechex($i), 64, '0', STR_PAD_LEFT), 'kind' => UserPushToken::KIND_APNS])
            ->forceFill(['updated_at' => now()->subMinutes(20 - $i)])->save();
    }
    $this->withToken($token)->postJson('/api/device/apns', ['token' => str_repeat('d4', 32)])->assertOk();
    expect(UserPushToken::query()->where('user_id', $user->id)->count())->toBe(UserPushToken::MAX_PER_USER)
        ->and(UserPushToken::query()->where('push_id', str_pad('1', 64, '0', STR_PAD_LEFT))->exists())->toBeFalse();
});

// QA-08 and the owner's answer 1
it('sends the 3-days-left reminder only for packages with washes left', function () {
    Queue::fake();
    $fleet = cwFleet();
    $package = Package::query()->create(['car_type' => 'sedan', 'count_washes' => 2]);
    qa11Device($fleet['driver'], 'ef');
    foreach ([2, 1] as $used) {
        UserPackage::query()->create(['user_id' => $fleet['driver']->id, 'user_car_id' => $fleet['userCar']->id, 'package_id' => $package->id,
            'body_type' => 'sedan', 'start_date' => now()->subDays(27), 'end_date' => now()->addDays(3), 'number_of_washes' => 2,
            'used_washes' => $used, 'qr_code' => Str::random(16)]);
    }

    $this->artisan('app:send-subscription-ending-notifications')->assertSuccessful();
    Queue::assertPushed(SendPush::class, 1);
});

it('respects the renewal switch for the "package ended" push', function () {
    Queue::fake();
    $fleet = cwFleet();
    $fleet['driver']->forceFill(['enable_push_renewal_subscription' => false])->save();
    qa11Device($fleet['driver'], 'ef');
    $package = Package::query()->create(['car_type' => 'sedan', 'count_washes' => 2]);
    UserPackage::query()->create(['user_id' => $fleet['driver']->id, 'user_car_id' => $fleet['userCar']->id, 'package_id' => $package->id,
        'body_type' => 'sedan', 'start_date' => now()->subDays(31), 'end_date' => now()->subMinute(), 'number_of_washes' => 2,
        'used_washes' => 0, 'qr_code' => Str::random(16)]);

    $this->artisan('user-packages:check')->assertSuccessful();
    Queue::assertNotPushed(SendPush::class);
    expect(UserPackage::query()->value('expiration_notified_at'))->not->toBeNull();
});

// QA-09
it('refuses emptying the manager of a branch instead of failing', function () {
    $f = qa11Booking('09');
    $this->actingAs(cwAdmin())->post(route('admin.washings.edit_save', $f['wash']), [
        'name' => 'QA branch', 'address' => 'a', 'work_time_start' => '09:00', 'work_time_end' => '20:00', 'location' => 'x', 'manager_id' => '',
    ])->assertSessionHasErrors('manager_id');
    expect((int) $f['wash']->fresh()->manager_id)->toBe($f['manager']->id);
});

// QA-10
it('answers fixed texts in the language the app names', function () {
    $user = makeUser('555 91 10 01');
    $this->withToken(cwAs($user))->withHeader('X-App-Lang', 'ka')->deleteJson('/api/appointments/999/remove')
        ->assertNotFound()->assertJsonPath('error', 'ჯავშანი ვერ მოიძებნა.');
    $this->withToken(cwAs($user))->withHeader('X-App-Lang', 'ka')->getJson('/api/packages/999/qr')
        ->assertNotFound()->assertJsonPath('error', 'ვერ მოიძებნა.');
    // An older build that names no language gets the answer as before.
    $this->flushHeaders();
    $this->withToken(cwAs($user))->getJson('/api/packages/999/qr')->assertNotFound()->assertJsonPath('error', 'Not found');
});

// QA-12
it('asks Flitt about a closed package page before opening a new one', function () {
    $f = qa11Booking('12');
    $package = Package::query()->create(['car_type' => 'sedan', 'count_washes' => 4]);
    $price = PackagePrice::query()->create(['package_id' => $package->id, 'month' => 1, 'price' => 40]);
    $old = TbcPayment::query()->create(['merchant_payment_id' => 'POLDPAGE00000001', 'user_id' => $f['client']->id, 'type' => 'package',
        'amount' => 40, 'currency' => 'GEL', 'status' => 'pending', 'payload' => ['car_id' => $f['car']->id, 'package_id' => $package->id,
            'body_type' => 'sedan', 'price_id' => $price->id, 'month' => 1, 'washes' => 4, 'checkout_url' => 'https://pay.example/old']]);
    $old->forceFill(['created_at' => now()->subHour()])->save();
    $flitt = Mockery::mock(FlittCheckout::class);
    $flitt->shouldReceive('status')->andReturn([]);
    $flitt->shouldReceive('checkoutUrl')->never();
    app()->instance(FlittCheckout::class, $flitt);

    // Flitt has no answer yet: no second page for the same package.
    $this->withToken(cwAs($f['client']))->postJson('/api/packages/buy', ['car_id' => $f['car']->id, 'package_id' => $package->id, 'sub_term' => 1, 'renewal' => false])
        ->assertStatus(422)->assertJsonPath('error', __('booking.payment_checking', [], 'en'));
    expect(TbcPayment::query()->count())->toBe(1);
});

// QA-13
it('voids live codes on a ban and gives no approve token for a banned account', function () {
    $fleet = cwFleet(10, 3);
    $manager = cwManager('555 91 13 01');
    $qr = cwDriverQr($this, $fleet);
    $this->actingAs(cwAdmin())->post(route('admin.clients.ban', $fleet['driver']))->assertRedirect();

    expect($fleet['car']->fresh()->qr_code)->toBeNull();
    $this->withToken(cwAs($manager))->getJson('/api/checkqr/'.$qr)->assertNotFound();
});
