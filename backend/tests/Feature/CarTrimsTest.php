<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserCar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CarTrimsTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Storage::fake('local');
        config(['services.carapi.url' => 'https://geoapi.test/api/v1', 'services.carapi.secret' => 'geoapi_sk_test']);
        Http::fake([
            'geoapi.test/api/v1/models/7/photo*' => Http::response('jpeg-bytes', 200, ['Content-Type' => 'image/jpeg']),
            'geoapi.test/api/v1/models/7/generations*' => Http::response(['data' => [
                ['id' => '70', 'name' => 'XV70', 'display_label' => 'XV70 (2017–2024)', 'year_start' => 2017, 'year_end' => 2024,
                    'body_type' => 'sedan', 'photo_url' => 'https://geoapi.test/api/v1/models/7/photo?generation=70'],
                ['id' => '80', 'name' => 'XV80', 'year_start' => 2024, 'year_end' => null, 'body_type' => 'sedan', 'photo_url' => null],
            ], 'meta' => ['total' => 2]]),
            'geoapi.test/api/v1/generations/70/modifications*' => Http::response(['data' => [
                ['id' => '701', 'name' => '2.5 AT', 'engine_volume_cc' => 2487, 'transmission' => 'automatic', 'year_start' => 2017, 'year_end' => 2024,
                    'specs' => ['engine' => ['fuel' => 'petrol', 'power_hp' => 206], 'transmission' => ['drive' => 'fwd']]],
            ], 'meta' => ['total' => 1]]),
            'geoapi.test/api/v1/brands/toyota/models*' => Http::response(['data' => [
                ['id' => '7', 'name' => 'Camry', 'photo_url' => 'https://geoapi.test/api/v1/models/7/photo'],
            ], 'meta' => ['total' => 1]]),
            'geoapi.test/api/v1/brands*' => Http::response(['data' => [
                ['id' => '501', 'name' => 'Toyota', 'slug' => 'toyota', 'logo_url_light' => 'https://geoapi.test/logos/toyota.svg'],
            ], 'meta' => ['total' => 1]]),
        ]);

        $user = new User;
        $user->forceFill(['name' => 'N', 'surname' => 'B', 'phone' => '555505051', 'password' => 'secret12',
            'role' => User::ROLE_USER, 'phone_verified_at' => now(), 'ref_code' => 'tr123'])->save();
        $this->token = auth('api')->login($user);
    }

    public function test_brands_carry_logos_and_models_hide_upstream_photo_urls(): void
    {
        $this->withToken($this->token)->getJson('/api/cars/brands')->assertOk()
            ->assertJsonPath('brands.0.logo', 'https://geoapi.test/logos/toyota.svg');
        $models = $this->withToken($this->token)->getJson('/api/cars/brands/501/models')->assertOk()->json('models');
        $this->assertArrayNotHasKey('photo_url', $models[0]);
        Http::assertSent(fn (HttpRequest $r) => $r->hasHeader('Authorization', 'Bearer geoapi_sk_test'));
    }

    public function test_generations_filter_by_year_and_link_our_photo_proxy(): void
    {
        $rows = $this->withToken($this->token)->getJson('/api/cars/models/7/generations?brand_id=501&year=2020')
            ->assertOk()->json('generations');
        $this->assertCount(1, $rows);
        $this->assertSame(70, $rows[0]['id']);
        $this->assertSame('XV70 (2017–2024)', $rows[0]['name']);
        $this->assertStringEndsWith('/api/cars/models/7/photo?generation=70', $rows[0]['photo']);

        $this->withToken($this->token)->getJson('/api/cars/models/7/generations?brand_id=999')->assertNotFound();
    }

    public function test_modifications_belong_to_the_models_generation(): void
    {
        $this->withToken($this->token)->getJson('/api/cars/models/7/generations/70/modifications?year=2020')->assertOk()
            ->assertJsonPath('modifications.0.id', 701)
            ->assertJsonPath('modifications.0.power_hp', 206);
        $this->withToken($this->token)->getJson('/api/cars/models/7/generations/999/modifications')->assertNotFound();
    }

    public function test_car_saves_trim_from_the_catalog_and_shows_its_photo(): void
    {
        $this->withToken($this->token)->postJson('/api/mycars/add', [
            'source' => 'carapi', 'brand_id' => 501, 'model_id' => 7, 'generation_id' => 70, 'modification_id' => 701,
            'plate' => 'AA140BB', 'year' => 2020,
        ])->assertOk()
            ->assertJsonPath('car.generation.name', 'XV70 (2017–2024)')
            ->assertJsonPath('car.modification.name', '2.5 AT')
            ->assertJsonPath('car.body_type', 'sedan');

        $car = UserCar::query()->where('plate', 'AA140BB')->firstOrFail();
        $this->assertSame('/api/cars/models/7/photo?generation=70', $car->photo);

        $this->get('/api/cars/models/7/photo?generation=70')->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        // Second view comes from disk, not from geoapi.ge.
        $this->get('/api/cars/models/7/photo?generation=70')->assertOk();
        $this->assertSame(1, collect(Http::recorded())->filter(fn ($pair) => str_contains($pair[0]->url(), '/photo'))->count());
    }

    public function test_wrong_trim_is_rejected(): void
    {
        $this->withToken($this->token)->postJson('/api/mycars/add', [
            'source' => 'carapi', 'brand_id' => 501, 'model_id' => 7, 'generation_id' => 999, 'plate' => 'AA141BB',
        ])->assertStatus(422);
        $this->withToken($this->token)->postJson('/api/mycars/add', [
            'source' => 'carapi', 'brand_id' => 501, 'model_id' => 7, 'generation_id' => 70, 'modification_id' => 999, 'plate' => 'AA141BB',
        ])->assertStatus(422);
    }

    public function test_photo_proxy_only_spends_quota_on_known_ids(): void
    {
        // Model nobody saved and a generation list nobody loaded: no upstream request.
        $this->get('/api/cars/models/7/photo')->assertNotFound();
        $this->get('/api/cars/models/7/photo?generation=70')->assertNotFound();
        Http::assertNothingSent();
    }
}
