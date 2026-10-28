<?php

use App\Jobs\SendPush;
use App\Models\BodyType;
use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\CarWash;
use App\Models\CarWashService;
use App\Models\Faq;
use App\Models\Package;
use App\Models\PackagePrice;
use App\Models\Partner;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserCar;
use App\Models\UserPackage;
use App\Models\UserPushToken;
use App\Models\UserVoucher;
use App\Models\Voucher;
use App\Models\VoucherCategory;
use App\Support\AppSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function controlsPackage(User $user, ?string $rectoken = 'REC1'): UserPackage
{
    $brand = CarBrand::create(['name' => 'Ctrl brand '.$user->id]);
    $model = CarModel::create(['name' => 'Ctrl model', 'brand_id' => $brand->id, 'type' => 'sedan']);
    $car = UserCar::create(['car_type' => 'sedan', 'body_status' => 'confirmed', 'user_id' => $user->id, 'model_id' => $model->id, 'plate' => 'CT'.$user->id.'AA']);
    $package = Package::create(['car_type' => 'sedan', 'count_washes' => 4]);
    $price = PackagePrice::create(['package_id' => $package->id, 'month' => 1, 'price' => 30]);

    return UserPackage::create(['user_id' => $user->id, 'user_car_id' => $car->id, 'package_id' => $package->id, 'price_id' => $price->id,
        'start_date' => now(), 'end_date' => now()->addMonth(), 'number_of_washes' => 4, 'used_washes' => 0,
        'qr_code' => 'CTRLQR'.$user->id, 'rectoken' => $rectoken]);
}

it('opens every new admin page', function () {
    $admin = makeUser('555950001', User::ROLE_ADMIN);
    foreach (['/dashboard/content', '/dashboard/push', '/dashboard/payments', '/dashboard/vouchers/uses',
        '/dashboard/vouchers/uses?state=active', '/dashboard/corporate/invoices/settings', '/dashboard/body-types'] as $url) {
        $this->actingAs($admin)->get($url)->assertOk();
    }
    $client = makeUser('555950016');
    $pack = controlsPackage($client);
    foreach (["/dashboard/clients/{$client->id}", "/dashboard/clients/{$client->id}/packages", '/dashboard/appointments',
        '/dashboard/brands', '/dashboard/brands/'.$pack->car->model->brand_id.'/models'] as $url) {
        $this->actingAs($admin)->get($url)->assertOk();
    }
});

it('edits the faq, contacts and services the app reads', function () {
    $admin = makeUser('555950002', User::ROLE_ADMIN);
    $user = makeUser('555950003');
    $token = auth('api')->login($user);

    $this->withToken($token)->getJson('/api/faq')->assertJsonCount(0, 'faq');
    $this->actingAs($admin)->post('/dashboard/content/faq', ['question' => 'Q1?', 'answer' => 'A1'])->assertRedirect();
    $this->withToken($token)->getJson('/api/faq')->assertJsonPath('faq.0.question', 'Q1?');
    $faq = Faq::query()->firstOrFail();
    $this->actingAs($admin)->post("/dashboard/content/faq/{$faq->id}", ['question' => 'Q2?', 'answer' => 'A2']);
    $this->withToken($token)->getJson('/api/faq')->assertJsonPath('faq.0.question', 'Q2?');

    $this->actingAs($admin)->post('/dashboard/content/contacts', ['address' => 'Tbilisi', 'email' => 'a@b.ge',
        'phone' => '+995 555', 'website' => 'autopass.ge', 'working_hours' => '09-21'])->assertRedirect();
    $this->withToken($token)->getJson('/api/contacts')->assertJsonPath('contacts.address', 'Tbilisi');

    $this->actingAs($admin)->post('/dashboard/content/services', ['name' => 'Polish'])->assertRedirect();
    $this->withToken($token)->getJson('/api/services-list')->assertJsonPath('services.0.name', 'Polish');
    $service = CarWashService::query()->firstOrFail();
    $this->actingAs($admin)->delete("/dashboard/content/services/{$service->id}");
    expect(CarWashService::query()->count())->toBe(0);
});

it('adjusts client points with a transaction and never below zero', function () {
    $admin = makeUser('555950004', User::ROLE_ADMIN);
    $client = makeUser('555950005');

    $this->actingAs($admin)->post("/dashboard/clients/{$client->id}/points", ['delta' => 15, 'comment' => 'Sorry'])->assertRedirect();
    $this->actingAs($admin)->post("/dashboard/clients/{$client->id}/points", ['delta' => -20, 'comment' => 'Too much'])
        ->assertSessionHas('error');
    $this->actingAs($admin)->post("/dashboard/clients/{$client->id}/points", ['delta' => -5, 'comment' => 'Fix']);

    expect((int) $client->fresh()->points)->toBe(10)
        ->and(Transaction::query()->where('user_id', $client->id)->where('system', 'Admin')->count())->toBe(2);
});

it('lets the driver and the admin switch auto-renewal', function () {
    $admin = makeUser('555950006', User::ROLE_ADMIN);
    $user = makeUser('555950007');
    $other = makeUser('555950008');
    $pack = controlsPackage($user);
    $noCard = controlsPackage($other, null);
    $token = auth('api')->login($user);

    $this->withToken($token)->getJson('/api/packages/my')->assertJsonPath('packages.0.renewal', true)
        ->assertJsonPath('packages.0.renewal_available', true);
    $this->withToken($token)->postJson("/api/packages/{$pack->id}/renewal", ['renewal' => false])->assertOk()->assertJsonPath('renewal', false);
    $this->withToken($token)->postJson("/api/packages/{$noCard->id}/renewal", ['renewal' => false])->assertForbidden();
    $this->withToken(auth('api')->login($other))->postJson("/api/packages/{$noCard->id}/renewal", ['renewal' => true])->assertStatus(422);

    $this->actingAs($admin)->post("/dashboard/clients/{$user->id}/packages/{$pack->id}/renewal")->assertRedirect();
    expect($pack->fresh()->renews())->toBeTrue();
});

it('sends a promo push only to users who allow promotions', function () {
    Queue::fake();
    $admin = makeUser('555950009', User::ROLE_ADMIN);
    $yes = makeUser('555950010');
    $yes->forceFill(['enable_push_special_promotions' => true])->save();
    $no = makeUser('555950011');
    $no->forceFill(['enable_push_special_promotions' => false])->save();
    UserPushToken::query()->create(['user_id' => $yes->id, 'push_id' => 'sub-yes']);
    UserPushToken::query()->create(['user_id' => $no->id, 'push_id' => 'sub-no']);

    $this->actingAs($admin)->post('/dashboard/push', ['title_ka' => 'აქცია', 'text_ka' => '-20%'])->assertRedirect();

    Queue::assertPushed(SendPush::class, 1);
});

it('registers an ios apns token through onesignal', function () {
    config(['OneSignal.app_id' => 'app', 'OneSignal.rest_api_key' => 'key']);
    Http::fake(['api.onesignal.com/*' => Http::response(['subscriptions' => [['id' => 'sub-123', 'type' => 'iOSPush', 'token' => 'abcdef0123456789abcdef0123456789']]], 201)]);
    $user = makeUser('555950012');

    $this->withToken(auth('api')->login($user))->postJson('/api/device/apns', ['token' => 'ABCDEF0123456789ABCDEF0123456789'])->assertOk();

    expect($user->pushTokens()->value('push_id'))->toBe('sub-123');
});

it('lists a partner its own redeemed vouchers', function () {
    $partner = Partner::query()->forceCreate(['name' => 'Shop', 'description' => 'd', 'login' => 'shop-ctrl',
        'email' => 'ctrl@example.com', 'password' => bcrypt('secret12'), 'password_must_change' => false]);
    $user = makeUser('555950013');
    $user->forceFill(['name' => 'Nino', 'surname' => 'Beridze'])->save();
    $category = VoucherCategory::query()->create(['name' => 'Fuel']);
    $voucher = Voucher::query()->create(['name' => 'Fuel -10%', 'description' => 'x', 'photo' => 'f.png', 'category_id' => $category->id, 'price' => 10]);
    UserVoucher::query()->create(['user_id' => $user->id, 'voucher_id' => $voucher->id, 'code' => 'USEDCT01'])
        ->forceFill(['used_at' => now(), 'used_by_partner_id' => $partner->id])->save();
    UserVoucher::query()->create(['user_id' => $user->id, 'voucher_id' => $voucher->id, 'code' => 'LIVECT01']);

    $token = $this->postJson('/api/partner/login', ['login' => 'shop-ctrl', 'password' => 'secret12'])->json('access_token');
    $this->withToken($token)->getJson('/api/partner/vouchers/history')->assertOk()
        ->assertJsonCount(1, 'vouchers')->assertJsonPath('vouchers.0.code', 'USEDCT01')->assertJsonPath('vouchers.0.client', 'Nino B.');
});

it('prints the admin invoice requisites over the env values', function () {
    $admin = makeUser('555950014', User::ROLE_ADMIN);
    config(['invoices.seller.name' => 'Env seller']);

    $this->actingAs($admin)->post('/dashboard/corporate/invoices/settings', ['name' => 'Panel seller', 'vat_rate' => 18])->assertRedirect();

    expect(AppSettings::invoices()['seller']['name'])->toBe('Panel seller')
        ->and((int) AppSettings::invoices()['vat_rate'])->toBe(18);
});

it('renames a brand and saves a model', function () {
    $admin = makeUser('555950015', User::ROLE_ADMIN);
    $brand = CarBrand::create(['name' => 'Toyta']);
    $model = CarModel::create(['name' => 'Prious', 'brand_id' => $brand->id, 'type' => 'sedan']);
    BodyType::query()->firstOrCreate(['name' => 'hatchback'], ['name_ka' => 'ჰეჩბეკი', 'name_en' => 'Hatchback', 'sort' => 99]);

    $this->actingAs($admin)->post("/dashboard/brands/{$brand->id}/rename", ['name' => 'Toyota'])->assertRedirect();
    $this->actingAs($admin)->post("/dashboard/brands/{$brand->id}/models/{$model->id}/save", ['name' => 'Prius', 'type' => 'hatchback'])->assertRedirect();

    expect($brand->fresh()->name)->toBe('Toyota')->and($model->fresh()->name)->toBe('Prius')->and($model->fresh()->type)->toBe('hatchback');
});

it('takes a review without a comment once per branch and answers 422 in the driver language', function () {
    $manager = makeUser('555940090', User::ROLE_MANAGER);
    $wash = CarWash::create(['name' => 'Review branch', 'address' => 'Addr', 'location' => '41.7,44.8', 'manager_id' => $manager->id, 'work_time_start' => '09:00', 'work_time_end' => '20:00']);
    $token = auth('api')->login(makeUser('555940091'));

    $this->withToken($token)->postJson('/api/myreviews/add', ['car_wash_id' => $wash->id, 'stars' => 5])
        ->assertOk()->assertJsonPath('success', true);
    $this->withToken($token)->withHeader('Accept-Language', 'en')
        ->postJson('/api/myreviews/add', ['car_wash_id' => $wash->id, 'stars' => 4, 'text' => 'again'])
        ->assertStatus(422);
    $this->withToken($token)->postJson('/api/myreviews/add', ['car_wash_id' => $wash->id, 'stars' => 9])
        ->assertStatus(422);
});

it('sends the referral link to the configured store', function () {
    config(['services.app_stores.ios' => 'https://apps.apple.com/app/id123']);
    $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)')
        ->get('/ref/ABCDE')->assertOk()->assertSee('https://apps.apple.com/app/id123', false);
});
