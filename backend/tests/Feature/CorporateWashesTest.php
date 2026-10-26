<?php

use App\Models\CorporateClient;
use App\Models\CorporatePackage;
use App\Models\CorporateWash;
use App\Models\CorporateWashMove;
use App\Services\CorporateWashes;
use App\Services\FleetInvites;
use Illuminate\Support\Facades\Hash;

it('credits a company package and corrects the pool, never below the washes on cars', function () {
    $fleet = cwFleet(0, 0);
    $corp = $fleet['corp'];
    $admin = cwAdmin();

    $this->actingAs($admin)->post('/dashboard/packages/corporate/store', [
        'corporate_client_id' => $corp->id, 'type' => 'washes', 'car_type' => 'sedan', 'title' => '100 რეცხვა', 'washes' => 100, 'price' => 1500, 'is_active' => 1,
    ])->assertSessionHasNoErrors();
    $package = CorporatePackage::query()->where('corporate_client_id', $corp->id)->firstOrFail();

    $this->actingAs($admin)->post('/dashboard/corporate/'.$corp->id.'/washes/credit', ['corporate_package_id' => $package->id])
        ->assertSessionHasNoErrors();
    expect(CorporateWashes::balance($corp))->toMatchArray(['total' => 100, 'allocated' => 0, 'free' => 100]);
    $credit = CorporateWashMove::query()->firstOrFail();
    expect($credit->type)->toBe('credit')->and($credit->amount)->toBe(100)->and($credit->balance_after)->toBe(100)
        ->and((int) $credit->corporate_package_id)->toBe((int) $package->id)->and((int) $credit->actor_id)->toBe((int) $admin->id);

    // Another company's package can't be credited here.
    $other = CorporateClient::forceCreate(['name' => 'Other', 'username' => 'other', 'password' => Hash::make('x'), 'session_version' => 1]);
    $foreign = $other->packages()->create(['title' => 'X', 'washes' => 5]);
    $foreign->forceFill(['car_type' => 'sedan'])->save();
    $this->actingAs($admin)->post('/dashboard/corporate/'.$corp->id.'/washes/credit', ['corporate_package_id' => $foreign->id])
        ->assertSessionHasErrors('corporate_package_id');

    expect(CorporateWashes::allocate($fleet['car'], 30, 'company'))->toBe('ok');
    $this->actingAs($admin)->post('/dashboard/corporate/'.$corp->id.'/washes/correct', ['amount' => -80, 'car_type' => 'sedan', 'note' => 'შეცდომა'])
        ->assertSessionHasErrors('washes');
    $this->actingAs($admin)->post('/dashboard/corporate/'.$corp->id.'/washes/correct', ['amount' => -70, 'car_type' => 'sedan'])
        ->assertSessionHasErrors('note');
    $this->actingAs($admin)->post('/dashboard/corporate/'.$corp->id.'/washes/correct', ['amount' => -70, 'car_type' => 'sedan', 'note' => 'შეცდომა'])
        ->assertSessionHasNoErrors();
    expect(CorporateWashes::balance($corp))->toMatchArray(['total' => 30, 'allocated' => 30, 'free' => 0]);

    $this->get('/dashboard/corporate/'.$corp->id)->assertOk()
        ->assertSee('100 რეცხვა')->assertSee(__('admin.cw_type_correction'))->assertSee('შეცდომა');
});

it('lets the company give washes to a car only from the free pool', function () {
    $fleet = cwFleet(10, 0);
    $session = ['corporate_client_id' => $fleet['corp']->id, 'corporate_session_version' => 1];
    $car = $fleet['car'];

    $this->withSession($session)->post('/partner/cars/'.$car->id.'/washes', ['washes' => 11])->assertSessionHasErrors('fleet');
    $this->withSession($session)->post('/partner/cars/'.$car->id.'/washes', ['washes' => 4])->assertSessionHasNoErrors();
    expect($car->fresh()->washes_left)->toBe(4)
        ->and(CorporateWashes::balance($fleet['corp']))->toMatchArray(['total' => 10, 'allocated' => 4, 'free' => 6]);

    $this->withSession($session)->post('/partner/cars/'.$car->id.'/washes', ['washes' => 1])->assertSessionHasNoErrors();
    $moves = CorporateWashMove::query()->orderBy('id')->get();
    expect($moves->pluck('amount')->all())->toBe([4, -3])
        ->and($moves->pluck('actor')->unique()->all())->toBe(['company'])
        ->and($moves->last()->car_balance_after)->toBe(1)->and($moves->last()->balance_after)->toBe(10);

    $this->withSession($session)->post('/partner/cars/'.$car->id.'/mode', ['mode' => 'discount'])->assertSessionHasNoErrors();
    expect($car->fresh()->mode)->toBe('discount');

    $this->withSession($session)->get('/partner/washes')->assertOk()->assertSee(__('admin.cw_type_allocate'))->assertDontSee('/washes/credit');

    // Another company's car is out of reach.
    $other = cwFleet(5, 0, 'BB123CC', CorporateClient::forceCreate(['name' => 'O', 'username' => 'o2', 'password' => Hash::make('x'), 'session_version' => 1, 'washes_balance' => 5]));
    $this->withSession($session)->post('/partner/cars/'.$other['car']->id.'/washes', ['washes' => 1])->assertNotFound();
});

it('sets mode and washes over the API and reports the balance', function () {
    $fleet = cwFleet(5, 0);
    $key = CorporateClient::makeToken();
    $fleet['corp']->forceFill(['api_token' => $key])->save();
    $headers = ['X-Api-Key' => $key];

    $this->patchJson('/api/corporate/vehicles/AA123BB', ['washes' => 6, 'phone' => '555 99 99 99'], $headers)
        ->assertStatus(422)->assertJsonPath('free', 5);
    // A refused request changes nothing, the phone included.
    expect($fleet['car']->fresh()->driver_phone)->toBe($fleet['car']->driver_phone);

    $this->patchJson('/api/corporate/vehicles/AA123BB', ['washes' => 3, 'mode' => 'washes'], $headers)
        ->assertOk()->assertJsonPath('washes', 3)->assertJsonPath('mode', 'washes');
    $this->patchJson('/api/corporate/vehicles/AA123BB', ['mode' => 'cash'], $headers)->assertStatus(422);
    $this->getJson('/api/corporate/balance', $headers)->assertOk()
        ->assertJson(['ok' => true, 'total' => 5, 'allocated' => 3, 'free' => 2]);
    $this->getJson('/api/corporate/vehicles', $headers)->assertOk()
        ->assertJsonPath('vehicles.0.washes', 3)->assertJsonPath('vehicles.0.mode', 'washes');
    expect(CorporateWashMove::query()->latest('id')->value('actor'))->toBe('api');

    // Deleting the car returns its washes to the pool.
    $this->deleteJson('/api/corporate/vehicles/AA123BB', [], $headers)->assertOk();
    expect(CorporateWashes::balance($fleet['corp']))->toMatchArray(['total' => 5, 'allocated' => 0, 'free' => 5])
        ->and(CorporateWashMove::query()->latest('id')->first()->only(['type', 'amount', 'plate']))
        ->toBe(['type' => 'release', 'amount' => -3, 'plate' => 'AA123BB']);
});

it('takes one wash from the car and the pool when the manager approves the company QR', function () {
    $fleet = cwFleet(100, 4);
    $manager = cwManager();

    $this->withToken(cwAs($fleet['driver']))->getJson('/api/mycars')->assertOk()
        ->assertJsonPath('cars.0.company', 'Wash Co')
        ->assertJsonPath('cars.0.company_car.mode', 'washes')
        ->assertJsonPath('cars.0.company_car.washes_left', 4);

    $qr = cwDriverQr($this, $fleet);
    expect($qr)->toStartWith('fc');

    $scan = $this->withToken(cwAs($manager))->getJson('/api/checkqr/'.$qr)->assertOk()
        ->assertJsonPath('kind', 'corporate')
        ->assertJsonPath('corporate.company', 'Wash Co')
        ->assertJsonPath('corporate.mode', 'washes')
        ->assertJsonPath('corporate.plate', 'AA123BB')
        ->assertJsonPath('corporate.car_type', 'sedan')
        ->assertJsonPath('corporate.washes_left', 4)
        ->assertJsonPath('corporate.company_washes', 100)
        ->assertJsonPath('corporate.can_approve', true);
    $token = $scan->json('approve_token');
    $id = $fleet['car']->id;

    $this->withToken(cwAs($manager))->postJson('/api/corporate-wash/'.$id.'/approve', ['approve_token' => $token])->assertOk()
        ->assertJsonPath('corporate.washes_left', 3)
        ->assertJsonPath('corporate.company_washes', 99);
    // Single use.
    $this->withToken(cwAs($manager))->postJson('/api/corporate-wash/'.$id.'/approve', ['approve_token' => $token])->assertForbidden();

    expect($fleet['car']->fresh()->washes_left)->toBe(3)
        ->and($fleet['corp']->fresh()->washes_balance)->toBe(99);
    $wash = CorporateWash::query()->firstOrFail();
    expect($wash->plate)->toBe('AA123BB')->and((int) $wash->manager_id)->toBe((int) $manager->id)
        ->and($wash->walk_in)->toBeTrue()->and((int) $wash->user_id)->toBe((int) $fleet['driver']->id);
    $move = CorporateWashMove::query()->where('type', 'wash')->firstOrFail();
    expect($move->amount)->toBe(-1)->and($move->balance_after)->toBe(99)->and($move->car_balance_after)->toBe(3)
        ->and((int) $move->corporate_wash_id)->toBe((int) $wash->id);

    // Another manager can't spend this manager's grant.
    $token = $this->withToken(cwAs($manager))->getJson('/api/checkqr/'.$qr)->json('approve_token');
    $this->withToken(cwAs(cwManager('555 80 00 02')))->postJson('/api/corporate-wash/'.$id.'/approve', ['approve_token' => $token])->assertForbidden();
    expect($fleet['car']->fresh()->washes_left)->toBe(3);
});

it('shows why a company car cannot wash and gives no approve token', function () {
    $fleet = cwFleet(10, 0);
    $manager = cwManager();
    $qr = cwDriverQr($this, $fleet);

    $this->withToken(cwAs($manager))->getJson('/api/checkqr/'.$qr)->assertOk()
        ->assertJsonPath('corporate.can_approve', false)
        ->assertJsonPath('corporate.reason', 'no_washes')
        ->assertJsonPath('approve_token', null);

    CorporateWashes::allocate($fleet['car'], 2, 'company');
    CorporateWashes::setMode($fleet['car'], 'discount');
    $this->withToken(cwAs($manager))->getJson('/api/checkqr/'.$qr)->assertOk()
        ->assertJsonPath('corporate.mode', 'discount')
        ->assertJsonPath('corporate.reason', 'discount_not_ready');

    CorporateWashes::setMode($fleet['car'], 'washes');
    $fleet['corp']->forceFill(['ban' => 1])->save();
    $this->withToken(cwAs($manager))->getJson('/api/checkqr/'.$qr)->assertOk()->assertJsonPath('corporate.reason', 'company_blocked');
    $this->withToken(cwAs($fleet['driver']))->get('/api/mycars/'.$fleet['userCar']->id.'/company-qr')->assertForbidden();

    // An approve with a grant from before the block is still refused by the debit itself.
    $fleet['corp']->forceFill(['ban' => 0])->save();
    $token = $this->withToken(cwAs($manager))->getJson('/api/checkqr/'.$qr)->json('approve_token');
    $fleet['corp']->forceFill(['ban' => 1])->save();
    $this->withToken(cwAs($manager))->postJson('/api/corporate-wash/'.$fleet['car']->id.'/approve', ['approve_token' => $token])
        ->assertStatus(422)->assertJsonPath('reason', 'company_blocked');
    expect($fleet['car']->fresh()->washes_left)->toBe(2);
});

it('stops an old QR after expiry, a driver change or a block', function () {
    $fleet = cwFleet(10, 2);
    $manager = cwManager();
    $qr = cwDriverQr($this, $fleet);

    $fleet['car']->forceFill(['qr_expires_at' => now()->subSecond()])->save();
    $this->withToken(cwAs($manager))->getJson('/api/checkqr/'.$qr)->assertStatus(410);

    $qr = cwDriverQr($this, $fleet);
    FleetInvites::setDriver($fleet['car']->fresh(), '555 77 77 77');
    $this->withToken(cwAs($manager))->getJson('/api/checkqr/'.$qr)->assertNotFound();
    // The old driver no longer gets a code either.
    $this->withToken(cwAs($fleet['driver']))->get('/api/mycars/'.$fleet['userCar']->id.'/company-qr')->assertNotFound();

    // A stranger's car id gives nothing.
    $stranger = makeUser('555 66 66 66');
    $this->withToken(cwAs($stranger))->get('/api/mycars/'.$fleet['userCar']->id.'/company-qr')->assertNotFound();
});

it('blocks a car and forgets its QR', function () {
    $fleet = cwFleet(10, 2);
    $qr = cwDriverQr($this, $fleet);
    FleetInvites::setBlocked($fleet['car']->fresh(), true);

    $this->withToken(cwAs(cwManager()))->getJson('/api/checkqr/'.$qr)->assertNotFound();
    $this->withToken(cwAs($fleet['driver']))->get('/api/mycars/'.$fleet['userCar']->id.'/company-qr')->assertNotFound();
});
