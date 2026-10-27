<?php

use App\Models\CorporateClient;
use App\Models\CorporatePackage;
use App\Models\FleetCar;
use App\Models\UserCar;
use App\Services\CorporateDiscounts;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;

function bodyCorp(): array
{
    $plain = CorporateClient::makeToken();
    $corp = CorporateClient::forceCreate([
        'name' => 'Body Co', 'api_token' => $plain, 'username' => 'bodyco', 'password' => Hash::make('corppass1'),
        'password_must_change' => false, 'session_version' => 1,
    ]);

    return [$corp, ['X-Api-Key' => $plain]];
}

it('takes the year over the API and keeps a car with several body types waiting for its driver', function () {
    fakeYearCatalog();
    [$corp, $key] = bodyCorp();

    $this->postJson('/api/corporate/vehicles', ['vehicles' => [
        ['plate' => 'AA111AA', 'brand' => 'Toyota', 'model' => 'Avensis', 'year' => 2021],
        ['plate' => 'AA222AA', 'brand' => 'Toyota', 'model' => 'Avensis', 'year' => 2016],
        ['plate' => 'AA333AA', 'brand' => 'Toyota', 'model' => 'Avensis', 'year' => 2016, 'body_type' => 'უნივერსალი'],
        ['plate' => 'AA444AA', 'brand' => 'Toyota', 'model' => 'Avensis', 'year' => 2016, 'body_type' => 'suv'],
        ['plate' => 'AA555AA', 'brand' => 'Toyota', 'model' => 'Avensis'],
        ['plate' => 'AA666AA', 'brand' => 'Toyota', 'model' => 'Avensis', 'year' => 2005],
    ]], $key)->assertOk()
        ->assertJsonPath('added', 3)
        ->assertJsonPath('pending', [['plate' => 'AA222AA', 'body_status' => 'choose', 'body_types' => ['sedan', 'wagon']]])
        ->assertJsonPath('skipped.0', ['plate' => 'AA444AA', 'reason' => 'body_type_invalid', 'body_types' => ['sedan', 'wagon']])
        ->assertJsonPath('skipped.1.reason', 'year_invalid')
        ->assertJsonPath('skipped.2.reason', 'year_invalid');

    $cars = FleetCar::query()->orderBy('plate')->get()->keyBy('plate');
    expect($cars['AA111AA']->only(['year', 'car_type', 'body_status', 'body_source', 'catalog_source', 'catalog_model_id', 'generation_id']))
        ->toBe(['year' => 2021, 'car_type' => 'suv', 'body_status' => 'confirmed', 'body_source' => 'catalog', 'catalog_source' => 'carapi', 'catalog_model_id' => 7, 'generation_id' => 73])
        ->and($cars['AA222AA']->only(['car_type', 'body_status', 'body_options']))->toBe(['car_type' => null, 'body_status' => 'choose', 'body_options' => ['sedan', 'wagon']])
        ->and($cars['AA333AA']->only(['car_type', 'body_source']))->toBe(['car_type' => 'wagon', 'body_source' => 'company']);

    // The list says which car's body type is confirmed and which waits for the driver.
    $this->getJson('/api/corporate/vehicles', $key)->assertOk()
        ->assertJsonPath('vehicles.1.plate', 'AA222AA')->assertJsonPath('vehicles.1.body_status', 'choose')
        ->assertJsonPath('vehicles.1.body_type', null)->assertJsonPath('vehicles.1.body_types', ['sedan', 'wagon'])
        ->assertJsonPath('vehicles.2.body_status', 'confirmed')->assertJsonPath('vehicles.2.body_type', 'wagon');
});

it('reads year and body type columns from a sheet and keeps the cars still to confirm', function () {
    fakeYearCatalog();
    [$corp] = bodyCorp();
    $csv = "ნომერი,მარკა,მოდელი,წელი,ძარა,ტელეფონი\nAA111AA,Toyota,Avensis,2016,Station wagon,\nAA222AA,Toyota,Avensis,2016,,\n";

    $this->withSession(['corporate_client_id' => $corp->id, 'corporate_session_version' => 1])
        ->post('/partner/import', ['file' => UploadedFile::fake()->createWithContent('fleet.csv', $csv)])
        ->assertSessionHas('fleet_import_errors', [])
        ->assertSessionHas('fleet_import_pending', [['row' => 3, 'plate' => 'AA222AA', 'body_status' => 'choose', 'body_types' => ['sedan', 'wagon']]]);
    expect(FleetCar::query()->where('plate', 'AA111AA')->value('car_type'))->toBe('wagon')
        ->and(FleetCar::query()->where('plate', 'AA222AA')->value('body_status'))->toBe('choose');
    $this->get('/partner/fleet')->assertOk()->assertSee(__('admin.fleet_import_pending'))->assertSee(__('admin.fleet_body_status_choose'));
});

it('fills the panel form from the catalog and shows the body type only for a year with several', function () {
    fakeYearCatalog();
    [$corp] = bodyCorp();
    $panel = $this->withSession(['corporate_client_id' => $corp->id, 'corporate_session_version' => 1]);

    $panel->get('/partner/fleet')->assertOk()->assertSee('fleet_body_group', false)->assertSee('Toyota');
    $panel->getJson('/partner/catalog/brands/501/models')->assertOk()->assertJsonPath('models.0', ['id' => 7, 'name' => 'Avensis', 'source' => 'carapi']);
    $pick = ['source' => 'carapi', 'brand_id' => 501, 'model_id' => 7];
    expect($panel->getJson('/partner/catalog/years?'.http_build_query($pick))->assertOk()->json('years'))->toContain(2016)->not->toContain(2005);
    $panel->getJson('/partner/catalog/body-types?'.http_build_query($pick + ['year' => 2016]))->assertOk()
        ->assertJsonPath('choose', true)->assertJsonCount(2, 'body_types');
    $panel->getJson('/partner/catalog/body-types?'.http_build_query($pick + ['year' => 2021]))->assertOk()->assertJsonPath('choose', false);

    // The body type field is optional: left empty, the car waits for the driver's pick.
    $form = ['phone' => '555 12 34 56', 'brand_id' => 501, 'model_id' => 7, 'year' => 2016];
    $panel->post('/partner/cars', $form + ['plate' => 'AA776AA'])->assertSessionHasNoErrors()
        ->assertSessionHas('message', __('admin.fleet_car_added_choose'));
    $panel->post('/partner/cars', $form + ['plate' => 'AA777AA', 'body_type' => 'suv'])->assertSessionHasErrors('body_type');
    $panel->post('/partner/cars', $form + ['plate' => 'AA777AA', 'body_type' => 'sedan'])->assertSessionHasNoErrors();
    expect(FleetCar::query()->where('plate', 'AA777AA')->value('car_type'))->toBe('sedan')
        ->and(FleetCar::query()->where('plate', 'AA776AA')->value('body_status'))->toBe('choose');

    // The catalog endpoints are the company's own: no session, no catalog.
    $this->flushSession();
    $this->get('/partner/catalog/brands/501/models')->assertRedirect('/partner/login');
});

it('prices the company car by the body type the company gave and hands it to the driver app', function () {
    fakeYearCatalog();
    [$corp, $key] = bodyCorp();
    $package = CorporatePackage::query()->forceCreate(['corporate_client_id' => $corp->id, 'title' => 'Disc', 'type' => CorporatePackage::TYPE_DISCOUNT, 'washes' => 0, 'is_active' => true]);
    $package->prices()->createMany([
        ['car_type' => 'sedan', 'full_price' => 30, 'percent' => 50],
        ['car_type' => 'wagon', 'full_price' => 40, 'percent' => 50],
    ]);
    $this->postJson('/api/corporate/vehicles', ['plate' => 'AA888AA', 'brand' => 'Toyota', 'model' => 'Avensis', 'year' => 2016,
        'body_type' => 'wagon', 'phone' => '555 12 34 56'], $key)->assertOk()->assertJsonPath('added', 1);
    $car = FleetCar::query()->where('plate', 'AA888AA')->firstOrFail();
    $car->forceFill(['mode' => FleetCar::MODE_DISCOUNT, 'corporate_package_id' => $package->id])->save();

    // Priced as a wagon before the driver ever signs in.
    expect((float) CorporateDiscounts::forCar($car->fresh())->full_price)->toBe(40.0);

    $driver = makeUser('555 12 34 56');
    $driver->forceFill(['password' => Hash::make('driverpass')])->save();
    $this->postJson('/api/login', ['phone' => '555123456', 'password' => 'driverpass'])->assertOk();
    $userCar = UserCar::query()->where('plate', 'AA888AA')->firstOrFail();
    expect((int) $userCar->user_id)->toBe((int) $driver->id)
        ->and($userCar->only(['year', 'car_type', 'generation_id']))->toBe(['year' => 2016, 'car_type' => 'wagon', 'generation_id' => 72])
        ->and($userCar->model->external_model_id)->toBe(7);
});
