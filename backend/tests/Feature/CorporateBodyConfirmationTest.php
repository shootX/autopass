<?php

use App\Models\CorporateClient;
use App\Models\CorporateWashMove;
use App\Models\CorporateWashPool;
use App\Models\FleetCar;
use App\Models\Package;
use App\Models\UserCar;
use App\Models\UserPackage;
use App\Services\CorporateQr;
use App\Services\CorporateWashes;
use App\Services\FleetInvites;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;

it('keeps a company car added before body types were required waiting for the driver, with no QR and no washes until the pick', function () {
    Queue::fake();
    fakeYearCatalog();
    $corp = CorporateClient::forceCreate(['name' => 'Body Co', 'api_token' => CorporateClient::makeToken(), 'username' => 'bodyco2',
        'password' => Hash::make('corppass1'), 'password_must_change' => false, 'session_version' => 1]);
    $key = ['X-Api-Key' => $corp->api_token];
    CorporateWashPool::query()->forceCreate(['corporate_client_id' => $corp->id, 'car_type' => 'wagon', 'balance' => 10]);
    $corp->forceFill(['washes_balance' => 10])->save();

    // Saved as a company car was before the company had to name the body type.
    $fleet = FleetCar::query()->forceCreate(['corporate_client_id' => $corp->id, 'plate' => 'CB101CB', 'brand' => 'Toyota', 'model' => 'Avensis',
        'source' => 'api', 'year' => 2016, 'catalog_source' => 'carapi', 'catalog_brand_id' => 501, 'catalog_model_id' => 7,
        'body_status' => 'choose', 'body_options' => ['sedan', 'wagon'], 'body_checked_at' => now()]);
    $fleet->setRelation('client', $corp);
    FleetInvites::setDriver($fleet, '555 72 00 01');
    $driver = makeUser('555 72 00 01');
    FleetInvites::activateFor($driver);
    $fleet = FleetCar::query()->where('plate', 'CB101CB')->firstOrFail();
    $userCar = UserCar::query()->findOrFail($fleet->user_car_id);
    expect($userCar->only(['body_status', 'body_options', 'car_type']))->toBe(['body_status' => 'choose', 'body_options' => ['sedan', 'wagon'], 'car_type' => null]);

    // Washes wait for the body type: no share, no QR, no approval.
    $this->patchJson('/api/corporate/vehicles/CB101CB', ['washes' => 2], $key)->assertStatus(422)->assertJsonPath('body_status', 'choose');
    $this->withToken(cwAs($driver))->get("/api/mycars/{$userCar->id}/company-qr")->assertForbidden()->assertJsonPath('reason', 'body_unconfirmed');
    $this->withToken(cwAs($driver))->getJson('/api/mycars')->assertOk()->assertJsonPath('cars.0.company_car.body_confirmed', false);
    $code = CorporateQr::issue($fleet);
    $this->withToken(cwAs(cwManager()))->getJson('/api/checkqr/'.$code)->assertOk()
        ->assertJsonPath('corporate.reason', 'body_unconfirmed')->assertJsonPath('approve_token', null);

    // The driver picks a wagon: both cars are confirmed, and nothing else changes.
    $this->withToken(cwAs($driver))->postJson("/api/mycars/{$userCar->id}/body", ['car_type' => 'wagon'])->assertOk();
    expect($fleet->fresh()->only(['car_type', 'body_status', 'body_source', 'washes_left']))
        ->toBe(['car_type' => 'wagon', 'body_status' => 'confirmed', 'body_source' => 'driver', 'washes_left' => 0])
        ->and(CorporateWashPool::query()->where('corporate_client_id', $corp->id)->pluck('balance', 'car_type')->all())->toBe(['wagon' => 10])
        ->and(CorporateWashMove::query()->count())->toBe(0);

    $this->withoutToken()->patchJson('/api/corporate/vehicles/CB101CB', ['washes' => 2], $key)->assertOk()->assertJsonPath('body_type', 'wagon');
    $this->withToken(cwAs($driver))->get("/api/mycars/{$userCar->id}/company-qr")->assertOk();
});

it('checks cars from before against geoapi.ge: a report first, and on apply a company car given washes of another type gives them back', function () {
    fakeYearCatalog();
    $fleet = cwFleet(10, 3);
    $fleet['car']->forceFill(['body_status' => 'unknown', 'catalog_source' => 'carapi', 'catalog_brand_id' => 501, 'catalog_model_id' => 7, 'year' => 2021])->save();
    $user = makeUser('555 72 00 02');
    $model = $fleet['userCar']->model_id;
    $hinted = UserCar::query()->create(['user_id' => $user->id, 'model_id' => $model, 'plate' => 'CB201CB', 'year' => 2016, 'car_type' => 'wagon']);
    $plain = UserCar::query()->create(['user_id' => $user->id, 'model_id' => $model, 'plate' => 'CB202CB', 'year' => 2016]);
    DB::table('car_models')->where('id', $model)->update(['source' => 'carapi', 'external_model_id' => 7]);
    DB::table('car_brands')->where('id', $fleet['userCar']->model->brand_id)->update(['source' => 'carapi', 'external_brand_id' => 501]);
    $package = UserPackage::query()->create(['user_id' => $user->id, 'user_car_id' => $plain->id, 'package_id' => Package::query()->create(['car_type' => 'sedan', 'count_washes' => 4])->id,
        'start_date' => now(), 'end_date' => now()->addMonth(), 'number_of_washes' => 4, 'used_washes' => 0, 'qr_code' => 'CBPKG1']);
    $package->forceFill(['body_legacy' => true])->save();

    $this->artisan('cars:check-bodies')
        ->expectsOutputToContain('App cars: confirmed 1, driver picks 1, unknown 0')
        ->expectsOutputToContain('Company cars: confirmed 1')
        ->expectsOutputToContain('CB202CB: running package')
        ->expectsOutputToContain('AA123BB (company car): 3 sedan washes go back')
        ->expectsOutputToContain('Nothing saved')
        ->assertSuccessful();
    expect($hinted->fresh()->body_status)->toBe('unknown')->and($fleet['car']->fresh()->washes_left)->toBe(3);

    $this->artisan('cars:check-bodies --apply')->assertSuccessful();
    expect($hinted->fresh()->only(['car_type', 'body_status', 'body_source']))->toBe(['car_type' => 'wagon', 'body_status' => 'confirmed', 'body_source' => 'legacy'])
        ->and($plain->fresh()->only(['car_type', 'body_status']))->toBe(['car_type' => null, 'body_status' => 'choose'])
        // The package bought before keeps working; nothing about it changed.
        ->and($package->fresh()->bodyAllowsWash())->toBeTrue()
        ->and($fleet['car']->fresh()->only(['car_type', 'body_status', 'washes_left']))->toBe(['car_type' => 'suv', 'body_status' => 'confirmed', 'washes_left' => 0])
        // The company still owns its 10 sedan washes; the 3 given to the car are free again.
        ->and(CorporateWashes::balance($fleet['corp']->fresh())['types'])->toBe(['sedan' => ['total' => 10, 'allocated' => 0, 'free' => 10]])
        ->and(CorporateWashMove::query()->latest('id')->first()->only(['type', 'amount', 'car_type']))->toBe(['type' => 'allocate', 'amount' => -3, 'car_type' => 'sedan']);
});

it('keeps every car, package and company balance when the body status arrives, and confirms nothing on its own', function () {
    $fleet = cwFleet(10, 3);
    $package = UserPackage::query()->create(['user_id' => $fleet['driver']->id, 'user_car_id' => $fleet['userCar']->id, 'package_id' => Package::query()->create(['car_type' => 'sedan', 'count_washes' => 4])->id,
        'start_date' => now(), 'end_date' => now()->addMonth(), 'number_of_washes' => 4, 'used_washes' => 1, 'qr_code' => 'CBPKG2']);
    $migration = require database_path('migrations/2026_10_27_000001_add_body_status_to_cars.php');
    $migration->down();
    $migration->up();

    expect(UserCar::query()->count())->toBe(1)->and(FleetCar::query()->count())->toBe(1)->and(UserPackage::query()->count())->toBe(1)
        ->and($fleet['userCar']->fresh()->only(['car_type', 'body_status']))->toBe(['car_type' => 'sedan', 'body_status' => 'unknown'])
        ->and($fleet['car']->fresh()->only(['car_type', 'body_status', 'washes_left']))->toBe(['car_type' => 'sedan', 'body_status' => 'unknown', 'washes_left' => 3])
        ->and($package->fresh()->only(['used_washes', 'body_legacy']))->toBe(['used_washes' => 1, 'body_legacy' => true])
        ->and(CorporateWashPool::query()->value('balance'))->toBe(10);
});
