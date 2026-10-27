<?php

use App\Models\Appointment;
use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\CorporateClient;
use App\Models\FleetCar;
use App\Models\Partner;
use App\Models\User;
use App\Models\UserCar;
use App\Services\Sms\MockSmsGateway;
use App\Services\WashReportQuery;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

function fiCorp(string $username = 'fleetcorp'): CorporateClient
{
    return CorporateClient::forceCreate([
        'name' => 'Fleet Co', 'api_token' => CorporateClient::makeToken(), 'username' => $username, 'password' => Hash::make('corppass1'),
        'password_must_change' => false, 'session_version' => 1,
    ]);
}

function fiPanel(CorporateClient $corp): array
{
    return ['corporate_client_id' => $corp->id, 'corporate_session_version' => (int) $corp->session_version];
}

function fiAdmin(): User
{
    return User::query()->where('role', User::ROLE_ADMIN)->first() ?? makeUser('555 70 00 99', User::ROLE_ADMIN);
}

function fiCatalog(): CarModel
{
    $brand = CarBrand::query()->forceCreate(['name' => 'Toyota']);

    return CarModel::query()->forceCreate(['name' => 'Camry', 'type' => 'sedan', 'brand_id' => $brand->id]);
}

function fiDriver(string $phone): User
{
    return User::forceCreate([
        'phone' => $phone, 'password' => Hash::make('driverpass'), 'role' => User::ROLE_USER,
        'ref_code' => substr(md5($phone), 0, 5), 'phone_verified_at' => now(),
    ]);
}

function fiBooking(User $user, UserCar $car, string $createdAt): Appointment
{
    $wash = DB::table('car_washes')->value('id') ?? DB::table('car_washes')->insertGetId([
        'name' => 'W', 'address' => 'a', 'work_time_start' => '09:00', 'work_time_end' => '20:00', 'location' => 'x',
        'manager_id' => makeUser('555 70 00 98', User::ROLE_MANAGER)->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $booking = Appointment::create(['user_id' => $user->id, 'car_wash_id' => $wash, 'car_id' => $car->id,
        'date' => now()->toDateString(), 'time' => '10:00', 'qr_code' => Str::random(12), 'approved' => 0]);
    DB::table('appointments')->where('id', $booking->id)->update(['created_at' => $createdAt]);

    return $booking;
}

beforeEach(function () {
    app(MockSmsGateway::class)->reset();
});

it('invites the driver by SMS when the admin adds a car with a phone', function () {
    $model = fiCatalog();
    $corp = fiCorp();

    $this->actingAs(fiAdmin())->post('/dashboard/corporate/'.$corp->id.'/cars', [
        'plate' => 'AA - 123 - BB', 'phone' => '+995 555 12 34 56', 'brand_id' => $model->brand_id, 'model_id' => $model->id, 'year' => 2020, 'body_type' => 'sedan',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $car = FleetCar::query()->where('plate', 'AA123BB')->firstOrFail();
    expect($car->status)->toBe(FleetCar::STATUS_INVITED)
        ->and($car->driver_phone)->toBe('555 12 34 56')
        ->and($car->invited_at)->not->toBeNull();

    $sms = app(MockSmsGateway::class)->messages;
    expect($sms)->toHaveCount(1)
        ->and($sms[0]['destination'])->toBe('995555123456')
        ->and($sms[0]['purpose'])->toBe('fleet_invite')
        ->and($sms[0]['content'])->toContain('Fleet Co')->toContain('AA - 123 - BB');

    $this->get('/dashboard/corporate/'.$corp->id)->assertOk()
        ->assertSee(__('admin.fleet_status_invited'))->assertSee('555 12 34 56')->assertSee('/cars/'.$car->id.'/invite', false);
});

it('requires a valid driver phone in the manual form', function () {
    $model = fiCatalog();
    $corp = fiCorp();
    $admin = fiAdmin();
    $fields = ['plate' => 'AA123BB', 'brand_id' => $model->brand_id, 'model_id' => $model->id, 'year' => 2020, 'body_type' => 'sedan'];

    $this->actingAs($admin)->post('/dashboard/corporate/'.$corp->id.'/cars', $fields)->assertSessionHasErrors('phone');
    $this->actingAs($admin)->post('/dashboard/corporate/'.$corp->id.'/cars', $fields + ['phone' => '12345'])->assertSessionHasErrors('phone');
    expect(FleetCar::query()->count())->toBe(0);
});

it('activates the car when the invited phone signs in and links a new app car', function () {
    $model = fiCatalog();
    $corp = fiCorp();
    $this->withSession(fiPanel($corp))->post('/partner/cars', [
        'plate' => 'AA123BB', 'phone' => '555123456', 'brand_id' => $model->brand_id, 'model_id' => $model->id, 'year' => 2020, 'body_type' => 'sedan',
    ])->assertSessionHasNoErrors();

    $driver = fiDriver('555 12 34 56');
    $this->postJson('/api/login', ['phone' => '555123456', 'password' => 'driverpass'])->assertOk();

    $car = FleetCar::query()->firstOrFail();
    $userCar = UserCar::query()->where('plate', 'AA123BB')->firstOrFail();
    expect($car->status)->toBe(FleetCar::STATUS_ACTIVE)
        ->and((int) $car->user_id)->toBe((int) $driver->id)
        ->and((int) $car->user_car_id)->toBe((int) $userCar->id)
        ->and((int) $userCar->user_id)->toBe((int) $driver->id)
        ->and((int) $userCar->model_id)->toBe((int) $model->id);

    $cars = $this->withToken(JWTAuth::fromUser($driver))->getJson('/api/mycars')->assertOk()->json('cars');
    expect($cars)->toHaveCount(1)->and($cars[0]['company'])->toBe('Fleet Co');
});

it('links the driver own app car and refuses a plate held by someone else', function () {
    $model = fiCatalog();
    $corp = fiCorp();
    $driver = fiDriver('555 12 34 56');
    $own = UserCar::query()->create(['user_id' => $driver->id, 'model_id' => $model->id, 'plate' => 'AA123BB']);
    $stranger = fiDriver('555 99 99 99');
    UserCar::query()->create(['user_id' => $stranger->id, 'model_id' => $model->id, 'plate' => 'CC456DD']);

    $this->withSession(fiPanel($corp))->post('/partner/cars', ['plate' => 'AA123BB', 'phone' => '555 12 34 56', 'brand_id' => $model->brand_id, 'model_id' => $model->id, 'year' => 2020, 'body_type' => 'sedan']);
    $this->withSession(fiPanel($corp))->post('/partner/cars', ['plate' => 'CC456DD', 'phone' => '555 12 34 56', 'brand_id' => $model->brand_id, 'model_id' => $model->id, 'year' => 2020, 'body_type' => 'sedan']);

    $this->withToken(JWTAuth::fromUser($driver))->getJson('/api/me')->assertOk();

    $linked = FleetCar::query()->where('plate', 'AA123BB')->first();
    $taken = FleetCar::query()->where('plate', 'CC456DD')->first();
    expect($linked->status)->toBe(FleetCar::STATUS_ACTIVE)->and((int) $linked->user_car_id)->toBe((int) $own->id)
        ->and($taken->status)->toBe(FleetCar::STATUS_INVITED)->and($taken->activation_error)->toBe('plate_taken')
        ->and(UserCar::query()->where('user_id', $driver->id)->count())->toBe(1);
});

it('shows the company only washes booked after activation, never a typed stranger plate', function () {
    $model = fiCatalog();
    $corp = fiCorp();
    $stranger = fiDriver('555 99 99 99');
    $strangerCar = UserCar::query()->create(['user_id' => $stranger->id, 'model_id' => $model->id, 'plate' => 'CC456DD']);
    fiBooking($stranger, $strangerCar, now()->toDateTimeString());

    // The old leak: typing someone's plate into the fleet showed their washes.
    $this->withSession(fiPanel($corp))->post('/partner/cars', ['plate' => 'CC456DD', 'phone' => '555 11 11 11', 'brand_id' => $model->brand_id, 'model_id' => $model->id, 'year' => 2020, 'body_type' => 'sedan']);
    expect(WashReportQuery::forFleet([], (int) $corp->id)->count())->toBe(0);

    $driver = fiDriver('555 12 34 56');
    $driverCar = UserCar::query()->create(['user_id' => $driver->id, 'model_id' => $model->id, 'plate' => 'AA123BB']);
    $before = fiBooking($driver, $driverCar, now()->subDay()->toDateTimeString());
    $this->withSession(fiPanel($corp))->post('/partner/cars', ['plate' => 'AA123BB', 'phone' => '555 12 34 56', 'brand_id' => $model->brand_id, 'model_id' => $model->id, 'year' => 2020, 'body_type' => 'sedan']);
    $this->travel(1)->minutes();
    $this->withToken(JWTAuth::fromUser($driver))->getJson('/api/me')->assertOk();
    $this->travel(1)->minutes();
    $after = fiBooking($driver, $driverCar, now()->toDateTimeString());

    $ids = WashReportQuery::forFleet([], (int) $corp->id)->pluck('id')->all();
    expect($ids)->toBe([$after->id])->not->toContain($before->id);
    expect(WashReportQuery::forFleet([], (int) $corp->id, 'CC456DD')->count())->toBe(0);
    $this->withSession(fiPanel($corp))->get('/partner/reports')->assertOk();
    $this->withSession(fiPanel($corp))->get('/partner')->assertOk();

    // A block hides washes booked from then on.
    $car = FleetCar::query()->where('plate', 'AA123BB')->first();
    $this->travel(1)->minutes();
    $this->withSession(fiPanel($corp))->post('/partner/cars/'.$car->id.'/block')->assertRedirect();
    $this->travel(1)->minutes();
    fiBooking($driver, $driverCar, now()->toDateTimeString());
    expect(WashReportQuery::forFleet([], (int) $corp->id)->pluck('id')->all())->toBe([$after->id]);
});

it('blocks, unblocks, resends and changes the driver from the panel', function () {
    $model = fiCatalog();
    $corp = fiCorp();
    $other = fiCorp('othercorp');
    $panel = fiPanel($corp);
    $this->withSession($panel)->post('/partner/cars', ['plate' => 'AA123BB', 'phone' => '555 12 34 56', 'brand_id' => $model->brand_id, 'model_id' => $model->id, 'year' => 2020, 'body_type' => 'sedan']);
    $car = FleetCar::query()->firstOrFail();

    $this->withSession($panel)->post('/partner/cars/'.$car->id.'/invite')->assertSessionHasErrors('fleet');
    $this->travel(2)->minutes();
    $this->withSession($panel)->post('/partner/cars/'.$car->id.'/invite')->assertSessionHasNoErrors();
    expect(app(MockSmsGateway::class)->messages)->toHaveCount(2);

    $this->withSession($panel)->post('/partner/cars/'.$car->id.'/block');
    expect($car->fresh()->status)->toBe(FleetCar::STATUS_BLOCKED);
    $this->travel(2)->minutes();
    $this->withSession($panel)->post('/partner/cars/'.$car->id.'/invite')->assertSessionHasErrors('fleet');
    $this->withSession($panel)->post('/partner/cars/'.$car->id.'/block');
    expect($car->fresh()->status)->toBe(FleetCar::STATUS_INVITED);

    $this->withSession($panel)->post('/partner/cars/'.$car->id.'/driver', ['phone' => 'bad'])->assertSessionHasErrors('fleet');
    $this->withSession($panel)->post('/partner/cars/'.$car->id.'/driver', ['phone' => '577 00 00 01'])->assertSessionHasNoErrors();
    expect($car->fresh()->driver_phone)->toBe('577 00 00 01')
        ->and(app(MockSmsGateway::class)->messages[2]['destination'])->toBe('995577000001');

    // Another company's car is not reachable.
    $this->withSession(fiPanel($other))->post('/partner/cars/'.$car->id.'/block')->assertNotFound();
    $this->withSession($panel)->get('/partner/fleet')->assertOk()->assertSee('577 00 00 01');
});

it('keeps the company car in the driver app', function () {
    $model = fiCatalog();
    $corp = fiCorp();
    $this->withSession(fiPanel($corp))->post('/partner/cars', ['plate' => 'AA123BB', 'phone' => '555 12 34 56', 'brand_id' => $model->brand_id, 'model_id' => $model->id, 'year' => 2020, 'body_type' => 'sedan']);
    $driver = fiDriver('555 12 34 56');
    $token = JWTAuth::fromUser($driver);
    $this->withToken($token)->getJson('/api/me')->assertOk();
    $userCar = UserCar::query()->where('plate', 'AA123BB')->firstOrFail();

    $this->withToken($token)->deleteJson('/api/mycars/'.$userCar->id.'/remove')->assertForbidden()->assertJsonPath('error_code', 'company_car');
    $this->withToken($token)->postJson('/api/mycars/'.$userCar->id.'/edit', ['plate' => 'ZZ999ZZ'])->assertForbidden();
    expect($userCar->fresh())->not->toBeNull()->and($userCar->fresh()->plate)->toBe('AA123BB');
});

it('takes the driver phone over the API and edits it with PATCH', function () {
    fiCatalog();
    $corp = fiCorp();
    $plain = CorporateClient::makeToken();
    $corp->forceFill(['api_token' => $plain])->save();
    $key = ['X-Api-Key' => $plain];

    $this->postJson('/api/corporate/vehicles', ['vehicles' => [
        ['plate' => 'AA123BB', 'brand' => 'toyota', 'model' => 'CAMRY', 'year' => 2020, 'body_type' => 'sedan', 'phone' => '555 12 34 56'],
        ['plate' => 'BB123CC', 'brand' => 'Toyota', 'model' => 'Camry', 'year' => 2020, 'body_type' => 'sedan', 'phone' => '12'],
        ['plate' => 'CC123DD', 'brand' => 'Lada', 'model' => 'Niva', 'year' => 2020],
    ]], $key)->assertOk()->assertJsonPath('added', 1)
        ->assertJsonPath('skipped.0.reason', 'phone_invalid')
        ->assertJsonPath('skipped.1.reason', 'unknown_model');

    $this->getJson('/api/corporate/vehicles', $key)->assertOk()
        ->assertJsonPath('vehicles.0.status', 'invited')->assertJsonPath('vehicles.0.phone', '555 12 34 56');

    $this->patchJson('/api/corporate/vehicles/AA-123-BB', ['phone' => 'nope'], $key)->assertStatus(422);
    $this->patchJson('/api/corporate/vehicles/AA123BB', ['phone' => '577 00 00 01'], $key)->assertOk()->assertJsonPath('invite', 'sent');
    $this->patchJson('/api/corporate/vehicles/AA123BB', ['blocked' => true], $key)->assertOk()->assertJsonPath('status', 'blocked');
    $this->patchJson('/api/corporate/vehicles/ZZ999ZZ', ['blocked' => true], $key)->assertNotFound();
    expect(FleetCar::query()->first()->driver_phone)->toBe('577 00 00 01');
});

it('lists import rows it skipped with the reason', function () {
    fiCatalog();
    $corp = fiCorp();
    $csv = "ნომერი,მარკა,მოდელი,წელი,ძარა,ტელეფონი\nAA123BB,Toyota,Camry,2020,სედანი,555 12 34 56\nBAD,Toyota,Camry,2020,sedan,555 12 34 57\nCC123DD,Lada,Niva,2020,sedan,\n";
    $file = UploadedFile::fake()->createWithContent('fleet.csv', $csv);

    $this->withSession(fiPanel($corp))->post('/partner/import', ['file' => $file])
        ->assertRedirect(route('partner.fleet'))
        ->assertSessionHas('fleet_import_errors', [
            ['row' => 3, 'plate' => 'BAD', 'reason' => 'invalid'],
            ['row' => 4, 'plate' => 'CC123DD', 'reason' => 'unknown_model'],
        ]);
    expect(FleetCar::query()->where('plate', 'AA123BB')->value('driver_phone'))->toBe('555 12 34 56');
    $this->get('/partner/fleet')->assertOk()->assertSee(__('admin.fleet_reason_unknown_model'));
});

it('gives vendors their own list and add page again', function () {
    Partner::query()->forceCreate(['name' => 'Vendor One', 'description' => 'coffee', 'login' => 'vendor1', 'password' => 'x', 'email' => 'v@example.com']);
    fiCorp();
    $admin = fiAdmin();

    $this->actingAs($admin)->get('/dashboard/partners')->assertOk()->assertSee('Vendor One')->assertSee(__('admin.partners'));
    $this->actingAs($admin)->get('/dashboard/partners/add')->assertOk()->assertSee(route('admin.partners.store'), false);
    $this->actingAs($admin)->get('/dashboard/corporate')->assertOk()
        ->assertSee(__('admin.corporate_clients'))->assertSee(route('admin.partners'), false)->assertSee(__('admin.active'));
    $this->actingAs($admin)->get('/dashboard')->assertOk()->assertSee(__('admin.corporate_clients'));
});

it('labels the temporary password button and keeps the company note', function () {
    $corp = fiCorp();
    $corp->forceFill(['note' => 'Pays on the 5th'])->save();

    $this->actingAs(fiAdmin())->get('/dashboard/corporate/'.$corp->id)->assertOk()
        ->assertSee(__('admin.temp_password_send'))->assertDontSee(__('admin.temp_password_sent'))
        ->assertSee('Pays on the 5th');
});

it('asks for eight characters when the company changes its password', function () {
    $corp = fiCorp();
    $this->withSession(fiPanel($corp))->post('/partner/account', [
        'username' => 'fleetcorp', 'current_password' => 'corppass1', 'password' => 'short12', 'password_confirmation' => 'short12',
    ])->assertSessionHasErrors('password');
});
