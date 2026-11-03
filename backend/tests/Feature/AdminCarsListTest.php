<?php

use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\CorporateClient;
use App\Models\FleetCar;
use App\Models\User;
use App\Models\UserCar;
use Illuminate\Support\Facades\Hash;

function adminCarsFixture(): array
{
    $admin = makeUser('555940001', User::ROLE_ADMIN);
    $owner = makeUser('555940002');
    $owner->forceFill(['name' => 'Nino', 'surname' => 'Owner'])->save();
    $driver = makeUser('555940003');
    $brand = CarBrand::create(['name' => 'Subaru']);
    $model = CarModel::create(['name' => 'Crosstrek', 'brand_id' => $brand->id, 'type' => 'suv']);
    $corp = CorporateClient::forceCreate([
        'name' => 'Cars List Co', 'api_token' => CorporateClient::makeToken(), 'username' => 'carslistco',
        'password' => Hash::make('corppass1'), 'password_must_change' => false, 'session_version' => 1,
    ]);
    $personal = UserCar::create(['user_id' => $owner->id, 'model_id' => $model->id, 'plate' => 'KX074XK', 'vin' => 'JF2GTABC0KH000001', 'color' => 'Gray']);
    $activated = UserCar::create(['user_id' => $driver->id, 'model_id' => $model->id, 'plate' => 'AA123BB']);
    $linked = FleetCar::create(['corporate_client_id' => $corp->id, 'plate' => 'AA123BB', 'brand' => 'Subaru', 'model' => 'Crosstrek']);
    $linked->forceFill(['status' => FleetCar::STATUS_ACTIVE, 'user_id' => $driver->id, 'user_car_id' => $activated->id])->save();
    $waiting = FleetCar::create(['corporate_client_id' => $corp->id, 'plate' => 'CC456DD', 'brand' => 'Toyota', 'model' => 'Prius']);
    $waiting->forceFill(['status' => FleetCar::STATUS_INVITED, 'driver_phone' => '995555940009'])->save();

    return compact('admin', 'owner', 'driver', 'corp', 'personal');
}

it('lists every car once with its owner and company', function () {
    $f = adminCarsFixture();

    $response = $this->actingAs($f['admin'])->get('/dashboard/cars')->assertOk();
    $response->assertSee('KX074XK')->assertSee('AA123BB')->assertSee('CC456DD')
        ->assertSee('Nino Owner')->assertSee('Cars List Co')->assertSee('JF2GTABC0KH000001')
        ->assertSee(route('admin.clients.show', $f['owner']), false)
        ->assertSee(route('admin.corporate.show', $f['corp']), false);
    expect($response->viewData('cars')->total())->toBe(3);
});

it('filters cars by kind, company and search', function () {
    $f = adminCarsFixture();
    $admin = $this->actingAs($f['admin']);

    expect($admin->get('/dashboard/cars?kind=personal')->viewData('cars')->pluck('plate')->all())->toBe(['KX074XK'])
        ->and($admin->get('/dashboard/cars?kind=fleet')->viewData('cars')->total())->toBe(2)
        ->and($admin->get('/dashboard/cars?company='.$f['corp']->id)->viewData('cars')->total())->toBe(2)
        ->and($admin->get('/dashboard/cars?search=kx-074')->viewData('cars')->pluck('plate')->all())->toBe(['KX074XK'])
        ->and($admin->get('/dashboard/cars?search=cars list')->viewData('cars')->total())->toBe(2)
        ->and($admin->get('/dashboard/cars?search=nino')->viewData('cars')->pluck('plate')->all())->toBe(['KX074XK']);
});

it('shows a listed colour in the admin language', function () {
    $f = adminCarsFixture();
    app()->setLocale('ka');

    $this->actingAs($f['admin'])->get('/dashboard/cars')->assertSee('ნაცრისფერი');
});

it('keeps the cars page for admins only', function () {
    $f = adminCarsFixture();

    $this->actingAs($f['owner'])->get('/dashboard/cars')->assertForbidden();
});
