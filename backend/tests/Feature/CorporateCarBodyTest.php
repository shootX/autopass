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

it('takes the year over the API and the body type only when the year has several', function () {
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
        ->assertJsonPath('added', 2)
        ->assertJsonPath('skipped.0', ['plate' => 'AA222AA', 'reason' => 'body_type_required', 'body_types' => ['sedan', 'wagon']])
        ->assertJsonPath('skipped.1.reason', 'body_type_invalid')
        ->assertJsonPath('skipped.2.reason', 'year_invalid')
        ->assertJsonPath('skipped.3.reason', 'year_invalid');

    $cars = FleetCar::query()->orderBy('plate')->get()->keyBy('plate');
    expect($cars['AA111AA']->only(['year', 'car_type', 'catalog_source', 'catalog_model_id', 'generation_id']))
        ->toBe(['year' => 2021, 'car_type' => 'suv', 'catalog_source' => 'carapi', 'catalog_model_id' => 7, 'generation_id' => 73])
        ->and($cars['AA333AA']->car_type)->toBe('wagon');
});

it('reads year and body type columns from a sheet', function () {
    fakeYearCatalog();
    [$corp] = bodyCorp();
    $csv = "ნომერი,მარკა,მოდელი,წელი,ძარა,ტელეფონი\nAA111AA,Toyota,Avensis,2016,Station wagon,\nAA222AA,Toyota,Avensis,2016,,\n";

    $this->withSession(['corporate_client_id' => $corp->id, 'corporate_session_version' => 1])
        ->post('/partner/import', ['file' => UploadedFile::fake()->createWithContent('fleet.csv', $csv)])
        ->assertSessionHas('fleet_import_errors', [['row' => 3, 'plate' => 'AA222AA', 'reason' => 'body_type_required', 'body_types' => ['sedan', 'wagon']]]);
    expect(FleetCar::query()->where('plate', 'AA111AA')->value('car_type'))->toBe('wagon');
    $this->get('/partner/fleet')->assertOk()->assertSee(__('admin.fleet_reason_body_type_required'));
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

    $form = ['plate' => 'AA777AA', 'phone' => '555 12 34 56', 'brand_id' => 501, 'model_id' => 7, 'year' => 2016];
    $panel->post('/partner/cars', $form)->assertSessionHasErrors('body_type');
    $panel->post('/partner/cars', $form + ['body_type' => 'sedan'])->assertSessionHasNoErrors();
    expect(FleetCar::query()->where('plate', 'AA777AA')->value('car_type'))->toBe('sedan');

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
