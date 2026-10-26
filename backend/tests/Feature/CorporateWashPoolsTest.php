<?php

use App\Models\CarModel;
use App\Models\CorporateInvoice;
use App\Models\CorporatePackage;
use App\Models\CorporateWashMove;
use App\Models\CorporateWashPool;
use App\Models\FleetCar;
use App\Models\UserCar;
use App\Services\CorporateWashes;
use Illuminate\Support\Facades\DB;

/** Wash Co with sedan AA123BB and SUV BB123CC, and 10 sedan and 5 SUV washes credited from packages. */
function poolFleet(): array
{
    $fleet = cwFleet();
    $suvModel = CarModel::query()->forceCreate(['name' => 'RAV4', 'type' => 'suv', 'brand_id' => $fleet['userCar']->model->brand_id]);
    $suv = cwFleet(0, 0, 'BB123CC', $fleet['corp']);
    $suv['userCar']->forceFill(['model_id' => $suvModel->id])->save();
    $suv['car']->forceFill(['car_type' => 'suv'])->save();
    foreach (['sedan' => 10, 'suv' => 5] as $type => $washes) {
        $package = new CorporatePackage(['title' => $type, 'washes' => $washes, 'price' => 100, 'is_active' => true]);
        $package->forceFill(['corporate_client_id' => $fleet['corp']->id, 'type' => 'washes', 'car_type' => $type])->save();
        expect(CorporateWashes::credit($fleet['corp'], 'credit', $washes, $package, null, cwAdmin()->id))->toBe('ok');
    }

    return ['corp' => $fleet['corp'], 'sedan' => $fleet, 'suv' => $suv];
}

it('keeps the washes per body type and gives a car only its own type', function () {
    $fleet = poolFleet();
    expect(CorporateWashes::balance($fleet['corp']))->toBe([
        'total' => 15, 'allocated' => 0, 'free' => 15,
        'types' => ['sedan' => ['total' => 10, 'allocated' => 0, 'free' => 10], 'suv' => ['total' => 5, 'allocated' => 0, 'free' => 5]],
    ])->and($fleet['corp']->fresh()->washes_balance)->toBe(15);

    // The SUV can't take sedan washes.
    expect(CorporateWashes::allocate($fleet['suv']['car'], 6, 'company'))->toBe('not_enough')
        ->and(CorporateWashes::allocate($fleet['suv']['car'], 5, 'company'))->toBe('ok')
        ->and(CorporateWashes::allocate($fleet['sedan']['car'], 10, 'company'))->toBe('ok')
        ->and(CorporateWashes::balance($fleet['corp'])['types']['suv'])->toBe(['total' => 5, 'allocated' => 5, 'free' => 0]);

    // The API says how many of the car's own type are free.
    $key = 'pool-key-'.str_repeat('x', 30);
    $fleet['corp']->forceFill(['api_token' => $key])->save();
    $this->patchJson('/api/corporate/vehicles/BB123CC', ['washes' => 7], ['X-Api-Key' => $key])->assertStatus(422)
        ->assertJsonPath('body_type', 'suv')->assertJsonPath('free', 0);
    $this->getJson('/api/corporate/balance', ['X-Api-Key' => $key])->assertOk()->assertJsonPath('types.sedan.allocated', 10);
});

it('takes a wash from the car type pool only', function () {
    $fleet = poolFleet();
    CorporateWashes::allocate($fleet['suv']['car'], 2, 'company');
    $manager = cwManager();
    $qr = cwDriverQr($this, $fleet['suv']);
    $token = $this->withToken(cwAs($manager))->getJson('/api/checkqr/'.$qr)->assertOk()
        ->assertJsonPath('corporate.car_type', 'suv')->assertJsonPath('corporate.company_washes', 5)->json('approve_token');

    $this->withToken(cwAs($manager))->postJson('/api/corporate-wash/'.$fleet['suv']['car']->id.'/approve', ['approve_token' => $token])->assertOk()
        ->assertJsonPath('corporate.washes_left', 1)->assertJsonPath('corporate.company_washes', 4);
    expect(CorporateWashPool::query()->pluck('balance', 'car_type')->all())->toBe(['sedan' => 10, 'suv' => 4])
        ->and($fleet['corp']->fresh()->washes_balance)->toBe(14)
        ->and(CorporateWashMove::query()->where('type', 'wash')->sole()->only(['car_type', 'balance_after']))->toBe(['car_type' => 'suv', 'balance_after' => 4]);

    // No sedan washes left on the car: sedan washes in the pool don't help an SUV.
    CorporateWashes::allocate($fleet['suv']['car'], 0, 'company');
    $this->withToken(cwAs($manager))->getJson('/api/checkqr/'.$qr)->assertOk()->assertJsonPath('corporate.reason', 'no_washes');
});

it('credits a manual amount, a correction and a paid invoice to the right body type', function () {
    $fleet = poolFleet();
    $admin = cwAdmin();
    $url = '/dashboard/corporate/'.$fleet['corp']->id.'/washes';

    $this->actingAs($admin)->post($url.'/credit', ['amount' => 3])->assertSessionHasErrors('car_type');
    $this->actingAs($admin)->post($url.'/credit', ['amount' => 3, 'car_type' => 'minivan'])->assertSessionHasNoErrors();
    $this->actingAs($admin)->post($url.'/correct', ['amount' => -2, 'car_type' => 'suv', 'note' => 'fix'])->assertSessionHasNoErrors();
    expect(CorporateWashPool::query()->orderBy('car_type')->pluck('balance', 'car_type')->all())->toBe(['minivan' => 3, 'sedan' => 10, 'suv' => 3]);

    $package = CorporatePackage::query()->where('car_type', 'suv')->sole();
    $this->actingAs($admin)->post('/dashboard/corporate/'.$fleet['corp']->id.'/invoices', ['corporate_package_id' => $package->id])->assertSessionHasNoErrors();
    $this->actingAs($admin)->post('/dashboard/corporate/invoices/'.CorporateInvoice::query()->sole()->id.'/paid')->assertSessionHasNoErrors();
    expect(CorporateWashPool::query()->where('car_type', 'suv')->value('balance'))->toBe(8);

    $this->actingAs($admin)->get('/dashboard/corporate/'.$fleet['corp']->id)->assertOk()
        ->assertSee(\App\Models\BodyType::labelFor('minivan'));
    // A wash package needs its body type.
    $this->actingAs($admin)->post('/dashboard/packages/corporate/store', [
        'corporate_client_id' => $fleet['corp']->id, 'type' => 'washes', 'title' => 'x', 'washes' => 5, 'is_active' => 1,
    ])->assertSessionHasErrors('car_type');
});

it('splits the washes a company had before by the body types of its cars', function () {
    $fleet = poolFleet();
    cwFleet(0, 0, 'CC123DD', $fleet['corp']);
    $migration = require database_path('migrations/2026_10_26_000013_create_corporate_wash_pools.php');
    $migration->down();
    // As before: one pool of 20 with 4 on the sedan and 3 on the SUV, no type on the cars.
    DB::table('corporate_clients')->where('id', $fleet['corp']->id)->update(['washes_balance' => 20]);
    FleetCar::query()->update(['car_type' => null]);
    FleetCar::query()->whereKey($fleet['sedan']['car']->id)->update(['washes_left' => 4]);
    FleetCar::query()->whereKey($fleet['suv']['car']->id)->update(['washes_left' => 3]);
    DB::table('corporate_packages')->delete();
    DB::table('corporate_packages')->insert(['corporate_client_id' => $fleet['corp']->id, 'type' => 'washes', 'title' => 'old',
        'washes' => 10, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

    $migration->up();

    // Two sedans and one SUV: the 13 free washes go to the sedans.
    expect(DB::table('corporate_wash_pools')->orderBy('car_type')->pluck('balance', 'car_type')->all())->toBe(['sedan' => 17, 'suv' => 3])
        ->and(FleetCar::query()->orderBy('plate')->pluck('car_type', 'plate')->all())->toBe(['AA123BB' => 'sedan', 'BB123CC' => 'suv', 'CC123DD' => 'sedan'])
        ->and(DB::table('corporate_packages')->value('car_type'))->toBe('sedan');
});

it('needs the body type of a car to give it washes', function () {
    $fleet = poolFleet();
    $fleet['sedan']['car']->forceFill(['car_type' => null])->save();
    UserCar::query()->whereKey($fleet['sedan']['userCar']->id)->update(['car_type' => null]);
    CarModel::query()->whereKey($fleet['sedan']['userCar']->model_id)->update(['type' => '']);

    expect(CorporateWashes::allocate($fleet['sedan']['car']->fresh(), 1, 'company'))->toBe('no_body_type');
});
