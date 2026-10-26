<?php

use App\Models\BodyType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function btMigration(): object
{
    return require database_path('migrations/2026_10_26_000009_reset_body_types.php');
}

it('holds the 16 body types with Georgian and English names and no photo', function () {
    expect(BodyType::query()->ordered()->pluck('name')->all())->toBe([
        'sedan', 'hatchback', 'liftback', 'coupe', 'convertible', 'wagon', 'suv', 'pickup',
        'minivan', 'van', 'panel_van', 'bus', 'truck', 'chassis_cab', 'flatbed_truck', 'tractor_unit',
    ])->and(Schema::hasColumn('body_types', 'image'))->toBeFalse();

    app()->setLocale('ka');
    expect(BodyType::labelFor('suv'))->toBe('ჯიპი')->and(BodyType::labelFor('unknown'))->toBe('unknown');
    BodyType::forgetLabels();
    app()->setLocale('en');
    expect(BodyType::labelFor('tractor_unit'))->toBe('Tractor unit');
});

it('moves old body types onto the new keys and keeps an unknown one that is in use', function () {
    $migration = btMigration();
    $migration->down();
    DB::table('body_types')->delete();
    $now = now();
    foreach (['sedan', 'crossover', 'Cabriolet', 'limo_x', 'spare'] as $name) {
        DB::table('body_types')->insert(['name' => $name, 'created_at' => $now, 'updated_at' => $now]);
    }
    $brand = DB::table('car_brands')->insertGetId(['name' => 'Test', 'created_at' => $now, 'updated_at' => $now]);
    foreach (['crossover', 'Cabriolet', 'limo_x'] as $type) {
        DB::table('car_models')->insert(['name' => 'M '.$type, 'brand_id' => $brand, 'type' => $type, 'created_at' => $now, 'updated_at' => $now]);
    }

    $migration->up();

    expect(DB::table('car_models')->orderBy('id')->pluck('type')->all())->toBe(['suv', 'convertible', 'limo_x'])
        ->and(BodyType::query()->where('name', 'spare')->exists())->toBeFalse()
        ->and(BodyType::query()->where('name', 'limo_x')->exists())->toBeTrue()
        ->and(BodyType::query()->count())->toBe(17);
});

it('adds a body type from the dashboard with a key made from its English name', function () {
    $this->actingAs(cwAdmin())->post('/dashboard/body-types/store', ['name_ka' => 'კვადროციკლი', 'name_en' => 'Quad bike'])
        ->assertSessionHasNoErrors();

    expect(BodyType::query()->where('name', 'quad_bike')->value('name_ka'))->toBe('კვადროციკლი');
});
