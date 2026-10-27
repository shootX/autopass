<?php

use App\Models\BodyType;
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

it('requires the body type over the API and takes the company word for it', function () {
    fakeYearCatalog();
    [$corp, $key] = bodyCorp();

    $this->postJson('/api/corporate/vehicles', ['vehicles' => [
        ['plate' => 'AA111AA', 'brand' => 'Toyota', 'model' => 'Avensis', 'year' => 2021, 'body_type' => 'suv'],
        ['plate' => 'AA222AA', 'brand' => 'Toyota', 'model' => 'Avensis', 'year' => 2016],
        ['plate' => 'AA333AA', 'brand' => 'Toyota', 'model' => 'Avensis', 'year' => 2016, 'body_type' => 'უნივერსალი'],
        ['plate' => 'AA444AA', 'brand' => 'Toyota', 'model' => 'Avensis', 'year' => 2016, 'body_type' => 'suv'],
        ['plate' => 'AA445AA', 'brand' => 'Toyota', 'model' => 'Avensis', 'year' => 2016, 'body_type' => 'boat'],
        ['plate' => 'AA555AA', 'brand' => 'Toyota', 'model' => 'Avensis', 'body_type' => 'sedan'],
        ['plate' => 'AA666AA', 'brand' => 'Toyota', 'model' => 'Avensis', 'year' => 2005, 'body_type' => 'sedan'],
    ]], $key)->assertOk()
        ->assertJsonPath('added', 3)
        ->assertJsonPath('pending', [])
        ->assertJsonPath('skipped.0', ['plate' => 'AA222AA', 'reason' => 'body_type_required'])
        ->assertJsonPath('skipped.1.reason', 'body_type_invalid')
        ->assertJsonCount(16, 'skipped.1.body_types')
        ->assertJsonPath('skipped.2.reason', 'year_invalid')
        ->assertJsonPath('skipped.3.reason', 'year_invalid');

    $cars = FleetCar::query()->orderBy('plate')->get()->keyBy('plate');
    expect($cars['AA111AA']->only(['year', 'car_type', 'body_status', 'body_source', 'catalog_source', 'catalog_model_id', 'generation_id']))
        ->toBe(['year' => 2021, 'car_type' => 'suv', 'body_status' => 'confirmed', 'body_source' => 'catalog', 'catalog_source' => 'carapi', 'catalog_model_id' => 7, 'generation_id' => 73])
        ->and($cars['AA333AA']->only(['car_type', 'body_status', 'body_source', 'generation_id']))
        ->toBe(['car_type' => 'wagon', 'body_status' => 'confirmed', 'body_source' => 'company', 'generation_id' => 72])
        // geoapi.ge has no SUV of 2016, but the company's word stands: it only prices the company's own washes.
        ->and($cars['AA444AA']->only(['car_type', 'body_status', 'body_source', 'body_options', 'generation_id']))
        ->toBe(['car_type' => 'suv', 'body_status' => 'confirmed', 'body_source' => 'company', 'body_options' => ['sedan', 'wagon'], 'generation_id' => null]);

    $this->getJson('/api/corporate/vehicles', $key)->assertOk()
        ->assertJsonPath('vehicles.1.plate', 'AA333AA')->assertJsonPath('vehicles.1.body_status', 'confirmed')
        ->assertJsonPath('vehicles.1.body_type', 'wagon');
});

it('requires the body type column of a sheet and lists the rows without one', function () {
    fakeYearCatalog();
    [$corp] = bodyCorp();
    $csv = "ნომერი,მარკა,მოდელი,წელი,ძარა,ტელეფონი\nAA111AA,Toyota,Avensis,2016,Station wagon,\nAA222AA,Toyota,Avensis,2016,,\nAA223AA,Toyota,Avensis,2016,boat,\n";

    $this->withSession(['corporate_client_id' => $corp->id, 'corporate_session_version' => 1])
        ->post('/partner/import', ['file' => UploadedFile::fake()->createWithContent('fleet.csv', $csv)])
        ->assertSessionHas('fleet_import_errors', [
            ['row' => 3, 'plate' => 'AA222AA', 'reason' => 'body_type_required'],
            ['row' => 4, 'plate' => 'AA223AA', 'reason' => 'body_type_invalid'],
        ]);
    expect(FleetCar::query()->where('plate', 'AA111AA')->first()->only(['car_type', 'body_status']))->toBe(['car_type' => 'wagon', 'body_status' => 'confirmed'])
        ->and(FleetCar::query()->count())->toBe(1);
    $this->get('/partner/fleet')->assertOk()->assertSee(__('admin.fleet_reason_body_type_required'));
});

it('fills the panel form from the catalog and always asks the body type', function () {
    fakeYearCatalog();
    [$corp] = bodyCorp();
    $panel = $this->withSession(['corporate_client_id' => $corp->id, 'corporate_session_version' => 1]);

    $panel->get('/partner/fleet')->assertOk()->assertSee('name="body_type" required', false)
        ->assertSee(BodyType::labelFor('tractor_unit'))->assertSee('Toyota');
    $panel->getJson('/partner/catalog/brands/501/models')->assertOk()->assertJsonPath('models.0', ['id' => 7, 'name' => 'Avensis', 'source' => 'carapi']);
    $pick = ['source' => 'carapi', 'brand_id' => 501, 'model_id' => 7];
    expect($panel->getJson('/partner/catalog/years?'.http_build_query($pick))->assertOk()->json('years'))->toContain(2016)->not->toContain(2005);
    $panel->getJson('/partner/catalog/body-types?'.http_build_query($pick + ['year' => 2016]))->assertOk()
        ->assertJsonPath('status', 'choose')->assertJsonCount(2, 'body_types');
    $panel->getJson('/partner/catalog/body-types?'.http_build_query($pick + ['year' => 2021]))->assertOk()
        ->assertJsonPath('status', 'confirmed')->assertJsonPath('body_types.0.key', 'suv');

    $form = ['phone' => '555 12 34 56', 'brand_id' => 501, 'model_id' => 7, 'year' => 2016];
    $panel->post('/partner/cars', $form + ['plate' => 'AA776AA'])->assertSessionHasErrors('body_type');
    $panel->post('/partner/cars', $form + ['plate' => 'AA776AA', 'body_type' => 'boat'])->assertSessionHasErrors('body_type');
    $panel->post('/partner/cars', $form + ['plate' => 'AA777AA', 'body_type' => 'sedan'])->assertSessionHasNoErrors()
        ->assertSessionHas('message', __('admin.fleet_car_added'));
    expect(FleetCar::query()->where('plate', 'AA777AA')->first()->only(['car_type', 'body_status', 'body_source']))
        ->toBe(['car_type' => 'sedan', 'body_status' => 'confirmed', 'body_source' => 'company'])
        ->and(FleetCar::query()->where('plate', 'AA776AA')->exists())->toBeFalse();

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
