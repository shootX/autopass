<?php

// The third review (2026-11-03): bookings that no longer block a wash, prices below zero, giveaways past their
// date or removed, the payment return page, late payment answers and how long a discount code stays held.

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\CorporateWash;
use App\Models\Package;
use App\Models\PackagePrice;
use App\Models\PromoCode;
use App\Models\TbcPayment;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserCar;
use App\Models\UserPackage;
use App\Models\UserTicket;
use App\Models\Voucher;
use App\Models\VoucherCategory;
use App\Services\Payments\FlittCheckout;
use App\Services\Payments\SettleTbcPayment;
use App\Support\AppSettings;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery\MockInterface;

/** A driver with a confirmed sedan and a running 4-wash package on it. */
function taDriver(string $phone, string $plate): array
{
    $brand = CarBrand::query()->firstOrCreate(['name' => 'Toyota']);
    $model = CarModel::query()->where('name', 'Camry')->first()
        ?? CarModel::query()->forceCreate(['name' => 'Camry', 'type' => 'sedan', 'brand_id' => $brand->id]);
    $driver = makeUser($phone);
    $car = UserCar::query()->create(['user_id' => $driver->id, 'model_id' => $model->id, 'plate' => $plate,
        'car_type' => 'sedan', 'body_status' => 'confirmed']);
    $package = Package::query()->firstOrCreate(['car_type' => 'sedan', 'count_washes' => 4]);
    $price = PackagePrice::query()->firstOrCreate(['package_id' => $package->id, 'month' => 1], ['price' => 40]);
    $bought = UserPackage::query()->create(['user_id' => $driver->id, 'user_car_id' => $car->id, 'package_id' => $package->id,
        'price_id' => $price->id, 'start_date' => now()->subDay(), 'end_date' => now()->addMonth(),
        'number_of_washes' => 4, 'used_washes' => 0, 'qr_code' => Str::random(16)]);

    return compact('driver', 'car', 'package', 'price', 'bought');
}

function taWashOf(User $manager): int
{
    return (int) DB::table('car_washes')->where('manager_id', $manager->id)->value('id');
}

function taBooking(User $driver, int $carId, int $washId, AppointmentStatus $status): Appointment
{
    return Appointment::create(['user_id' => $driver->id, 'car_wash_id' => $washId, 'car_id' => $carId,
        'date' => now()->toDateString(), 'time' => '10:00', 'qr_code' => Str::random(12), 'approved' => $status->value]);
}

function taFlitt(): MockInterface
{
    $flitt = Mockery::mock(FlittCheckout::class)->makePartial();
    $flitt->shouldReceive('checkoutUrl')->andReturnUsing(fn (TbcPayment $p) => 'https://pay.example/'.$p->merchant_payment_id);
    app()->instance(FlittCheckout::class, $flitt);

    return $flitt;
}

function taPointsPayment(User $user, string $orderId, array $extra = []): TbcPayment
{
    return TbcPayment::query()->create($extra + ['merchant_payment_id' => $orderId, 'user_id' => $user->id, 'type' => 'points',
        'amount' => 5, 'currency' => 'GEL', 'status' => 'pending', 'payload' => ['points' => 50]]);
}

// 10. Bookings

it('lets a package wash at another branch when the booking there was cancelled or is done', function () {
    $f = taDriver('555 74 00 01', 'TA100TA');
    $vake = cwManager('555 74 00 02');
    $saburtalo = cwManager('555 74 00 03');
    $booking = taBooking($f['driver'], $f['car']->id, taWashOf($vake), AppointmentStatus::Pending);
    $approve = fn (User $manager) => $this->withToken(cwAs($manager))
        ->postJson('/api/package/'.$f['bought']->id.'/approve', ['approve_token' => approveTokenFor($manager, $f['bought']->id)]);

    // A booking still on at another branch keeps the wash there.
    $approve($saburtalo)->assertForbidden();

    $booking->update(['approved' => AppointmentStatus::Cancelled->value]);
    $approve($saburtalo)->assertOk();

    // The next one the same day: the booking now done blocks nothing either.
    $booking->update(['approved' => AppointmentStatus::Completed->value]);
    $approve($saburtalo)->assertOk();
    expect($f['bought']->fresh()->used_washes)->toBe(2);
});

it('lets a company car wash at another branch when the booking there is done', function () {
    $fleet = cwFleet(10, 5, 'TA200TA');
    $vake = cwManager('555 74 00 04');
    $saburtalo = cwManager('555 74 00 05');
    taBooking($fleet['driver'], $fleet['userCar']->id, taWashOf($vake), AppointmentStatus::Completed);

    $qr = cwDriverQr($this, $fleet);
    $token = $this->withToken(cwAs($saburtalo))->getJson('/api/checkqr/'.$qr)->assertOk()->json('approve_token');
    $this->withToken(cwAs($saburtalo))->postJson('/api/corporate-wash/'.$fleet['car']->id.'/approve', ['approve_token' => $token])->assertOk();

    expect($fleet['car']->fresh()->washes_left)->toBe(4)
        ->and((int) CorporateWash::query()->value('car_wash_id'))->toBe(taWashOf($saburtalo));
});

// 1. Prices below zero

it('refuses a price below zero in the admin and never sells one', function () {
    $admin = cwAdmin();
    $category = VoucherCategory::query()->create(['name' => 'Fuel']);
    $this->actingAs($admin)->post('/dashboard/vouchers/store', [
        'category_id' => $category->id, 'name' => 'Minus', 'voucher_type' => 1, 'description' => 'gives points',
        'photo' => UploadedFile::fake()->image('v.png'), 'price' => -10, 'footer_text' => 'f', 'footer_geo' => 'g',
    ])->assertSessionHasErrors('price');
    $this->actingAs($admin)->post('/dashboard/tickets/store', [
        'name' => 'Minus', 'description' => 'gives points', 'photo' => UploadedFile::fake()->image('t.png'),
        'price' => -10, 'date_to' => now()->addWeek()->toDateString(),
    ])->assertSessionHasErrors('price');
    expect(Voucher::query()->count())->toBe(0)->and(Ticket::query()->count())->toBe(0);

    $voucher = fn () => Voucher::query()->create(['name' => 'Old', 'description' => 'x', 'photo' => 'o.png', 'category_id' => $category->id, 'price' => -10]);
    $ticket = fn () => DB::table('tickets')->insertGetId(['name' => 't', 'description' => 'd', 'photo' => 'p', 'price' => -10,
        'date_to' => now()->addWeek()->toDateString(), 'created_at' => now(), 'updated_at' => now()]);
    if (DB::getDriverName() === 'pgsql') {
        // PostgreSQL refuses such a row itself.
        expect(fn () => DB::transaction($voucher))->toThrow(QueryException::class)
            ->and(fn () => DB::transaction($ticket))->toThrow(QueryException::class);

        return;
    }

    // One saved that way before (or typed into the database) is not sold.
    $user = makeUser('555 74 00 06');
    $user->forceFill(['points' => 5])->save();
    $voucher = $voucher();
    $ticket = $ticket();
    $token = cwAs($user);

    $this->withToken($token)->postJson('/api/vouchers/buy', ['voucher_id' => $voucher->id])->assertJson(['success' => false]);
    $this->withToken($token)->postJson('/api/tickets/buy', ['ticket_id' => $ticket, 'qty' => 2])->assertJson(['success' => false]);
    expect((int) $user->fresh()->points)->toBe(5)
        ->and(UserTicket::query()->count())->toBe(0)
        ->and(DB::table('user_vouchers')->count())->toBe(0);
});

// 7. Giveaways

it('stops selling a giveaway after its date and keeps the tickets bought when the admin removes it', function () {
    $user = makeUser('555 74 00 07');
    $user->forceFill(['points' => 100])->save();
    $ticket = fn (string $dateTo) => DB::table('tickets')->insertGetId(['name' => 'Draw '.$dateTo, 'description' => 'd', 'photo' => 'p',
        'price' => 5, 'date_to' => $dateTo, 'created_at' => now(), 'updated_at' => now()]);
    $past = $ticket(now()->subDay()->toDateString());
    $today = $ticket(now()->toDateString());
    $token = cwAs($user);

    expect(collect($this->withToken($token)->getJson('/api/tickets')->assertOk()->json('tickets.data'))->pluck('id')->all())->toBe([$today])
        ->and(collect($this->withToken($token)->getJson('/api/shop/home')->assertOk()->json('tickets'))->pluck('id')->all())->toBe([$today]);
    $this->withToken($token)->postJson('/api/tickets/buy', ['ticket_id' => $past, 'qty' => 1])->assertJson(['success' => false, 'message' => 'Ticket expired']);
    $this->withToken($token)->postJson('/api/tickets/buy', ['ticket_id' => $today, 'qty' => 2])->assertJson(['success' => true]);
    expect((int) $user->fresh()->points)->toBe(90);

    $this->actingAs(cwAdmin())->delete('/dashboard/tickets/'.$today.'/delete')->assertRedirect();

    $this->assertSoftDeleted('tickets', ['id' => $today]);
    expect(UserTicket::query()->where('ticket_id', $today)->count())->toBe(2);
    $mine = $this->withToken(cwAs($user))->getJson('/api/tickets/my')->assertOk()->json('tickets.data');
    expect($mine)->toHaveCount(1)->and($mine[0]['id'])->toBe($today)->and((int) $mine[0]['qty'])->toBe(2);
    // Removed: no longer listed or sold.
    expect($this->withToken(cwAs($user))->getJson('/api/tickets')->json('tickets.data'))->toBe([]);
    $this->withToken(cwAs($user))->postJson('/api/tickets/buy', ['ticket_id' => $today, 'qty' => 1])->assertJson(['success' => false]);
});

// 3. Payment return page

it('asks Flitt from the return page only about an own order still waiting', function () {
    $user = makeUser('555 74 00 08');
    $flitt = taFlitt();
    $paid = taPointsPayment($user, 'PTARET1', ['status' => 'succeeded', 'paid_at' => now()]);
    $open = taPointsPayment($user, 'PTARET2');
    $flitt->shouldNotReceive('status')->with('MADEUP');
    $flitt->shouldNotReceive('status')->with('PTARET1');
    $flitt->shouldReceive('status')->with('PTARET2')->once()->andReturn(['order_id' => 'PTARET2', 'order_status' => 'approved', 'amount' => 500]);

    $this->get('/payment/return?order_id=MADEUP')->assertRedirect();
    $this->get('/payment/return?order_id=PTARET1')->assertRedirect();
    $this->get('/payment/return?order_id=PTARET2')->assertRedirect();

    expect($open->fresh()->status)->toBe('succeeded')
        ->and((int) $user->fresh()->points)->toBe(50)
        ->and($paid->fresh()->status)->toBe('succeeded');
});

// 2. Late payment answers

it('never turns a paid payment into a failed one, nor grants it twice', function () {
    $user = makeUser('555 74 00 09');
    $flitt = taFlitt();
    $settle = app(SettleTbcPayment::class);
    $payment = taPointsPayment($user, 'PTALATE');
    $answer = fn (string $status) => $settle->fromFlitt(['order_id' => 'PTALATE', 'order_status' => $status, 'amount' => 500], $flitt);

    $answer('approved');
    expect($payment->fresh()->status)->toBe('succeeded')->and((int) $user->fresh()->points)->toBe(50);

    // A late decline, an expiry or a reversal changes nothing: money and points are not taken back.
    foreach (['declined', 'expired', 'reversed'] as $late) {
        $answer($late);
    }
    $settle->fromFlitt(['order_id' => 'PTALATE', 'order_status' => 'approved', 'amount' => 1], $flitt);
    expect($payment->fresh()->status)->toBe('succeeded')->and((int) $user->fresh()->points)->toBe(50);

    // A payment granted once is never granted again, even if an older answer had marked it failed.
    $payment->forceFill(['status' => 'declined'])->save();
    $answer('approved');
    $settle->grantNow($payment->fresh());
    expect((int) $user->fresh()->points)->toBe(50);
});

// 13. Discount code held by a payment

it('holds a discount code until a payment made near its page end is settled', function () {
    $flitt = taFlitt();
    AppSettings::store(['point_price' => 1, 'point_discount' => 0]);
    PromoCode::query()->create(['code' => 'LAST', 'kind' => 'discount', 'value' => 90, 'max_uses' => 1]);
    $a = makeUser('555 74 00 10');
    $b = makeUser('555 74 00 11');
    $this->withToken(cwAs($a))->postJson('/api/points/buy', ['amount' => 50, 'promo_code' => 'LAST'])->assertOk();
    $first = TbcPayment::query()->sole();

    // The page closed, but it may have been paid in its last minute: still held for everyone else.
    $this->travel(TbcPayment::CHECKOUT_MINUTES + 10)->minutes();
    $this->withToken(cwAs($b))->postJson('/api/points/buy', ['amount' => 50, 'promo_code' => 'LAST'])->assertStatus(422)
        ->assertJsonPath('code', 'promo_used_up');
    // Its owner waits too while Flitt can't say whether the closed page was paid (fourth review, point 3).
    $flitt->shouldReceive('status')->with($first->merchant_payment_id)->once()->andThrow(new RuntimeException('Flitt down'));
    $this->withToken(cwAs($a))->postJson('/api/points/buy', ['amount' => 50, 'promo_code' => 'LAST'])->assertStatus(422)
        ->assertJsonPath('code', 'promo_checking');
    expect(TbcPayment::query()->count())->toBe(1);

    // Flitt says it expired unpaid: the code is free again.
    $flitt->shouldReceive('status')->with($first->merchant_payment_id)
        ->andReturn(['order_id' => $first->merchant_payment_id, 'order_status' => 'expired', 'amount' => $flitt->minor($first)]);
    $this->artisan('payments:reconcile')->assertSuccessful();
    expect($first->fresh()->status)->toBe('expired');
    $this->withToken(cwAs($b))->postJson('/api/points/buy', ['amount' => 50, 'promo_code' => 'LAST'])->assertOk();
});

it('keeps a discount reserved when nobody could settle its payment after two hours', function () {
    taFlitt();
    AppSettings::store(['point_price' => 1, 'point_discount' => 0]);
    PromoCode::query()->create(['code' => 'STUCK', 'kind' => 'discount', 'value' => 90, 'max_uses' => 1]);
    $this->withToken(cwAs(makeUser('555 74 00 12')))->postJson('/api/points/buy', ['amount' => 50, 'promo_code' => 'STUCK'])->assertOk();
    $b = makeUser('555 74 00 13');

    $this->travel(TbcPayment::SETTLE_MINUTES - 1)->minutes();
    $this->withToken(cwAs($b))->postJson('/api/points/buy', ['amount' => 50, 'promo_code' => 'STUCK'])->assertStatus(422);
    $this->travel(2)->minutes();
    $this->withToken(cwAs($b))->postJson('/api/points/buy', ['amount' => 50, 'promo_code' => 'STUCK'])->assertStatus(422);
});

it('settles payments up to a week old', function () {
    $user = makeUser('555 74 00 14');
    $flitt = taFlitt();
    $old = taPointsPayment($user, 'PTAWEEK');
    $old->forceFill(['created_at' => now()->subDays(5)])->save();
    $flitt->shouldReceive('status')->with('PTAWEEK')->andReturn(['order_id' => 'PTAWEEK', 'order_status' => 'approved', 'amount' => 500]);

    $this->artisan('payments:reconcile')->assertSuccessful();

    expect($old->fresh()->status)->toBe('succeeded')->and((int) $user->fresh()->points)->toBe(50);
});
