<?php

namespace Tests\Feature;

use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\User;
use App\Models\UserCar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CarCatalogIdsTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['services.carapi.url' => 'https://carapi.test/api/v1', 'services.carapi.secret' => 'k']);
        Http::fake([
            'carapi.test/api/v1/models/*/generations*' => Http::response(['data' => []]),
            'carapi.test/api/v1/brands/toyota/models*' => Http::response(['data' => [['id' => 7, 'name' => 'Camry']], 'meta' => ['total' => 1]]),
            'carapi.test/api/v1/brands*' => Http::response(['data' => [['id' => 501, 'name' => 'Toyota', 'slug' => 'toyota']], 'meta' => ['total' => 1]]),
        ]);

        $user = new User;
        $user->forceFill(['name' => 'N', 'surname' => 'B', 'phone' => '555505050', 'password' => 'secret12',
            'role' => User::ROLE_USER, 'phone_verified_at' => now(), 'ref_code' => 'cc123'])->save();
        $this->token = auth('api')->login($user);
    }

    private function localModelWithId7(): CarModel
    {
        $brand = CarBrand::query()->create(['name' => 'Lada']);
        $model = new CarModel(['name' => 'Niva', 'brand_id' => $brand->id, 'type' => 'suv']);
        $model->id = 7;
        $model->save();

        return $model;
    }

    public function test_catalog_items_carry_their_source(): void
    {
        $this->withToken($this->token)->getJson('/api/cars/brands')->assertOk()
            ->assertJsonPath('brands.0.id', 501)->assertJsonPath('brands.0.source', 'carapi');
        $this->withToken($this->token)->getJson('/api/cars/brands/501/models')->assertOk()
            ->assertJsonPath('models.0.id', 7)->assertJsonPath('models.0.source', 'carapi');

        config(['services.carapi.secret' => '']);
        Cache::flush();
        $local = CarBrand::query()->create(['name' => 'Opel']);
        $this->withToken($this->token)->getJson('/api/cars/brands')->assertOk()
            ->assertJsonPath('brands.0.id', $local->id)->assertJsonPath('brands.0.source', 'local');
    }

    public function test_external_model_id_never_resolves_to_a_colliding_local_id(): void
    {
        $niva = $this->localModelWithId7();

        $this->withToken($this->token)->postJson('/api/mycars/add', [
            'source' => 'carapi', 'brand_id' => 501, 'model_id' => 7, 'plate' => 'AA123BB', 'year' => 2019,
        ])->assertOk();

        $car = UserCar::query()->where('plate', 'AA123BB')->firstOrFail();
        $this->assertNotSame($niva->id, (int) $car->model_id);
        $model = CarModel::query()->findOrFail($car->model_id);
        $this->assertSame('Camry', $model->name);
        $this->assertSame('carapi', $model->source);
        $this->assertSame(7, (int) $model->external_model_id);
        $this->assertSame(501, (int) $model->brand->external_brand_id);
        $this->assertSame(2019, (int) $car->year);

        // Same external model again reuses the mapped local row.
        $this->withToken($this->token)->postJson('/api/mycars/add', [
            'source' => 'carapi', 'brand_id' => 501, 'model_id' => 7, 'plate' => 'AA124BB',
        ])->assertOk();
        $this->assertSame(1, CarModel::query()->where('source', 'carapi')->where('external_model_id', 7)->count());
    }

    public function test_local_source_uses_local_id_and_ambiguous_ids_are_rejected(): void
    {
        $niva = $this->localModelWithId7();

        $this->withToken($this->token)->postJson('/api/mycars/add', ['model_id' => 7, 'plate' => 'AA125BB'])
            ->assertStatus(422);
        $this->withToken($this->token)->postJson('/api/mycars/add', [
            'source' => 'carapi', 'brand_id' => 501, 'model_id' => 999, 'plate' => 'AA125BB',
        ])->assertStatus(422);

        $this->withToken($this->token)->postJson('/api/mycars/add', ['source' => 'local', 'model_id' => 7, 'plate' => 'AA125BB'])
            ->assertOk();
        $this->assertSame($niva->id, (int) UserCar::query()->where('plate', 'AA125BB')->value('model_id'));
    }

    public function test_year_is_validated_listed_and_returned(): void
    {
        $now = (int) now()->year;
        $years = $this->withToken($this->token)->getJson('/api/cars/years')->assertOk()->json('years');
        $this->assertSame($now + 1, $years[0]);
        $this->assertContains(1980, $years);

        $this->withToken($this->token)->postJson('/api/mycars/add', [
            'source' => 'carapi', 'brand_id' => 501, 'model_id' => 7, 'plate' => 'AA126BB', 'year' => 1800,
        ])->assertStatus(422);

        $this->withToken($this->token)->postJson('/api/mycars/add', [
            'source' => 'carapi', 'brand_id' => 501, 'model_id' => 7, 'plate' => 'AA126BB', 'year' => 2020,
        ])->assertOk();
        $car = UserCar::query()->where('plate', 'AA126BB')->firstOrFail();
        $this->withToken($this->token)->postJson('/api/mycars/'.$car->id.'/edit', ['year' => 2021])->assertOk();
        $this->withToken($this->token)->getJson('/api/mycars')->assertOk()
            ->assertJsonPath('cars.0.year', 2021)
            ->assertJsonPath('cars.0.model.source', 'carapi')
            ->assertJsonPath('cars.0.model.external_model_id', 7);
    }

    public function test_existing_rows_are_marked_local_after_migration(): void
    {
        $niva = $this->localModelWithId7();
        $this->assertSame('local', $niva->fresh()->source);
        $this->assertNull($niva->fresh()->external_model_id);
        $this->assertSame('local', $niva->brand->fresh()->source);
    }

    public function test_local_model_must_belong_to_the_given_local_brand(): void
    {
        $niva = $this->localModelWithId7();
        $other = CarBrand::query()->create(['name' => 'Opel']);

        $this->withToken($this->token)->postJson('/api/mycars/add', [
            'source' => 'local', 'brand_id' => $other->id, 'model_id' => $niva->id, 'plate' => 'AA130BB',
        ])->assertStatus(422);
        $this->withToken($this->token)->postJson('/api/mycars/add', [
            'source' => 'local', 'brand_id' => $niva->brand_id, 'model_id' => $niva->id, 'plate' => 'AA130BB',
        ])->assertOk();
    }

    public function test_offline_fallback_checks_the_external_brand(): void
    {
        $this->withToken($this->token)->postJson('/api/mycars/add', [
            'source' => 'carapi', 'brand_id' => 501, 'model_id' => 7, 'plate' => 'AA131BB',
        ])->assertOk();

        // CarAPI goes down: a known external model may only be reused under its own external brand.
        config(['services.carapi.secret' => '']);
        Cache::flush();
        $this->withToken($this->token)->postJson('/api/mycars/add', [
            'source' => 'carapi', 'brand_id' => 999, 'model_id' => 7, 'plate' => 'AA132BB',
        ])->assertStatus(422);
        $this->withToken($this->token)->postJson('/api/mycars/add', [
            'source' => 'carapi', 'brand_id' => 501, 'model_id' => 7, 'plate' => 'AA132BB',
        ])->assertOk();
    }

    public function test_parallel_add_of_the_same_new_external_model_does_not_500(): void
    {
        // Simulate the other request winning the insert between our lookup and our create.
        $raced = false;
        CarModel::creating(function (CarModel $model) use (&$raced) {
            if ($raced || $model->external_model_id === null) {
                return;
            }
            $raced = true;
            DB::table('car_models')->insert([
                'name' => 'Camry', 'type' => $model->type, 'brand_id' => $model->brand_id,
                'source' => 'carapi', 'external_model_id' => $model->external_model_id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        $this->withToken($this->token)->postJson('/api/mycars/add', [
            'source' => 'carapi', 'brand_id' => 501, 'model_id' => 7, 'plate' => 'AA133BB',
        ])->assertOk();
        $this->assertTrue($raced);
        $this->assertSame(1, CarModel::query()->where('source', 'carapi')->where('external_model_id', 7)->count());
        $this->assertSame(
            (int) CarModel::query()->where('external_model_id', 7)->value('id'),
            (int) UserCar::query()->where('plate', 'AA133BB')->value('model_id'),
        );
    }
}
