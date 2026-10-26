<?php

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\CarWash;
use App\Models\Package;
use App\Models\PackagePrice;
use App\Models\PackageWash;
use App\Models\Review;
use App\Models\TbcPayment;
use App\Models\User;
use App\Models\UserCar;
use App\Models\UserPackage;
use App\Services\ClientProfile;

function clientProfileFixture(): array
{
    $admin = makeUser('555930001', User::ROLE_ADMIN);
    $client = makeUser('555930002');
    $manager = makeUser('555930003', User::ROLE_MANAGER);
    $wash = CarWash::create(['name' => 'Profile branch', 'address' => 'Addr',
        'work_time_start' => '09:00', 'work_time_end' => '20:00', 'location' => 'Loc', 'manager_id' => $manager->id]);
    $brand = CarBrand::create(['name' => 'Profile brand']);
    $model = CarModel::create(['name' => 'Profile model', 'brand_id' => $brand->id, 'type' => 'sedan']);
    $car = UserCar::create(['user_id' => $client->id, 'model_id' => $model->id, 'plate' => 'PR123OF']);
    $package = Package::create(['car_type' => 'sedan', 'count_washes' => 8]);
    $price = PackagePrice::create(['package_id' => $package->id, 'month' => 1, 'price' => 40]);
    $userPackage = UserPackage::create([
        'user_id' => $client->id, 'user_car_id' => $car->id, 'package_id' => $package->id, 'price_id' => $price->id,
        'start_date' => now()->subDays(20), 'end_date' => now()->addDays(10),
        'number_of_washes' => 8, 'used_washes' => 2, 'qr_code' => 'PROFILEQR0001',
    ]);

    return compact('admin', 'client', 'manager', 'wash', 'car', 'userPackage');
}

function clientProfileBooking(array $f, int $status, string $date): Appointment
{
    return Appointment::create(['user_id' => $f['client']->id, 'car_wash_id' => $f['wash']->id, 'car_id' => $f['car']->id,
        'date' => $date, 'time' => '10:00', 'qr_code' => substr(md5($date.$status), 0, 12), 'approved' => $status]);
}

it('opens the client profile from the client list for admins only', function () {
    $f = clientProfileFixture();

    $this->actingAs($f['admin'])->get('/dashboard/clients')
        ->assertOk()->assertSee(route('admin.clients.show', $f['client']), false);
    $this->actingAs($f['admin'])->get('/dashboard/clients/'.$f['client']->id)
        ->assertOk()->assertSee('PR123OF')->assertSee('Profile branch');

    $this->actingAs($f['manager'])->get('/dashboard/clients/'.$f['client']->id)->assertForbidden();
    $this->actingAs($f['admin'])->get('/dashboard/clients/'.$f['manager']->id)->assertNotFound();
});

it('counts visits, money and booking reliability without double counting', function () {
    $f = clientProfileFixture();
    $done = clientProfileBooking($f, AppointmentStatus::Completed->value, now()->subDays(10)->toDateString());
    clientProfileBooking($f, AppointmentStatus::Completed->value, now()->subDays(4)->toDateString());
    clientProfileBooking($f, AppointmentStatus::Cancelled->value, now()->subDays(3)->toDateString());
    clientProfileBooking($f, AppointmentStatus::Expired->value, now()->subDays(2)->toDateString());
    // The first completed booking was served from the package: one visit, not two.
    PackageWash::create(['user_package_id' => $f['userPackage']->id, 'car_wash_id' => $f['wash']->id,
        'manager_id' => $f['manager']->id, 'appointment_id' => $done->id]);
    PackageWash::create(['user_package_id' => $f['userPackage']->id, 'car_wash_id' => $f['wash']->id,
        'manager_id' => $f['manager']->id, 'walk_in' => true]);
    TbcPayment::create(['merchant_payment_id' => 'PROFILEPAID1', 'user_id' => $f['client']->id, 'type' => 'package',
        'amount' => 40, 'currency' => 'GEL', 'status' => 'succeeded', 'payload' => []]);
    TbcPayment::create(['merchant_payment_id' => 'PROFILETEST1', 'user_id' => $f['client']->id, 'type' => 'package',
        'amount' => 99, 'currency' => 'GEL', 'status' => 'succeeded', 'payload' => [], 'test_mode' => true]);
    TbcPayment::create(['merchant_payment_id' => 'PROFILEFAIL1', 'user_id' => $f['client']->id, 'type' => 'package',
        'amount' => 55, 'currency' => 'GEL', 'status' => 'Failed', 'payload' => []]);
    Review::forceCreate(['user_id' => $f['client']->id, 'car_wash_id' => $f['wash']->id, 'stars' => 4, 'text' => 'ok']);
    $f['client']->forceFill(['created_at' => now()->subDays(90)])->save();

    $data = (new ClientProfile($f['client']->fresh()))->data();
    $kpi = $data['kpi'];

    expect($kpi['ltv'])->toBe(40.0)
        ->and($kpi['visits'])->toBe(3)
        ->and($kpi['usage_pct'])->toBe(25)
        ->and($kpi['bookings'])->toBe(4)
        ->and($kpi['cancel_pct'])->toBe(25)
        ->and($kpi['no_show_pct'])->toBe(25)
        ->and($kpi['favourite_wash']->id)->toBe($f['wash']->id)
        ->and($kpi['avg_stars'])->toBe(4.0)
        ->and($kpi['active_package']->id)->toBe($f['userPackage']->id)
        ->and($data['segment'])->toBe('active')
        ->and($data['isVip'])->toBeTrue();
});

it('labels new, never-bought and lapsed clients', function () {
    $f = clientProfileFixture();
    expect((new ClientProfile($f['client']))->data()['segment'])->toBe('new');

    $idle = makeUser('555930004');
    $idle->forceFill(['created_at' => now()->subDays(90)])->save();
    expect((new ClientProfile($idle->fresh()))->data()['segment'])->toBe('never_bought');

    clientProfileBooking($f, AppointmentStatus::Completed->value, now()->subDays(75)->toDateString());
    $f['client']->forceFill(['created_at' => now()->subDays(120)])->save();
    expect((new ClientProfile($f['client']->fresh()))->data()['segment'])->toBe('lapsed');
});
