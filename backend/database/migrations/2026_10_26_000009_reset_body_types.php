<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Body types lose their photo, get a Georgian and an English name, and become the fixed list of 16.
 * `name` stays the key that car_models.type, packages.car_type and corporate_package_prices.car_type hold.
 * An old type is moved onto its new key; an old type with no match is deleted when unused and kept otherwise.
 */
return new class extends Migration
{
    private const TYPES = [
        'sedan' => ['სედანი', 'Sedan'],
        'hatchback' => ['ჰეჩბექი', 'Hatchback'],
        'liftback' => ['ლიფტბექი', 'Liftback'],
        'coupe' => ['კუპე', 'Coupe'],
        'convertible' => ['კაბრიოლეტი', 'Convertible'],
        'wagon' => ['უნივერსალი', 'Wagon'],
        'suv' => ['ჯიპი', 'SUV'],
        'pickup' => ['პიკაპი', 'Pickup'],
        'minivan' => ['მინივენი', 'Minivan'],
        'van' => ['ფურგონი', 'Van'],
        'panel_van' => ['დახურული ფურგონი', 'Panel van'],
        'bus' => ['ავტობუსი', 'Bus'],
        'truck' => ['სატვირთო', 'Truck'],
        'chassis_cab' => ['შასი კაბინით', 'Chassis cab'],
        'flatbed_truck' => ['ბორტიანი სატვირთო', 'Flatbed truck'],
        'tractor_unit' => ['საწევარი', 'Tractor unit'],
    ];

    private const ALIASES = [
        'crossover' => 'suv', 'jeep' => 'suv', 'offroad' => 'suv', 'off_road' => 'suv',
        'cabriolet' => 'convertible', 'cabrio' => 'convertible', 'roadster' => 'convertible',
        'estate' => 'wagon', 'universal' => 'wagon', 'station_wagon' => 'wagon', 'kombi' => 'wagon',
        'pick_up' => 'pickup',
        'fastback' => 'liftback',
        'mpv' => 'minivan', 'compact_van' => 'minivan',
        'minibus' => 'bus', 'microbus' => 'bus',
        'cargo_van' => 'panel_van',
        'lorry' => 'truck',
        'chassis' => 'chassis_cab',
        'flatbed' => 'flatbed_truck',
        'tractor' => 'tractor_unit', 'semi' => 'tractor_unit', 'semi_truck' => 'tractor_unit',
        'limousine' => 'sedan',
    ];

    public function up(): void
    {
        Schema::table('body_types', function (Blueprint $table) {
            $table->string('name_ka', 64)->nullable();
            $table->string('name_en', 64)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
        });

        DB::transaction(function () {
            $now = now();
            foreach (DB::table('body_types')->get() as $old) {
                $key = $this->keyFor($old->name);
                if ($key === null) {
                    $this->dropOrKeep($old);

                    continue;
                }
                if ($key === $old->name) {
                    continue;
                }
                $this->move($old->name, $key);
                if (DB::table('body_types')->where('name', $key)->exists()) {
                    DB::table('body_types')->where('id', $old->id)->delete();
                } else {
                    DB::table('body_types')->where('id', $old->id)->update(['name' => $key, 'updated_at' => $now]);
                }
            }

            $sort = 0;
            foreach (self::TYPES as $key => [$ka, $en]) {
                $values = ['name_ka' => $ka, 'name_en' => $en, 'sort' => ++$sort, 'updated_at' => $now];
                if (DB::table('body_types')->where('name', $key)->exists()) {
                    DB::table('body_types')->where('name', $key)->update($values);
                } else {
                    DB::table('body_types')->insert($values + ['name' => $key, 'created_at' => $now]);
                }
            }
        });

        Schema::table('body_types', function (Blueprint $table) {
            $table->dropColumn('image');
        });
    }

    public function down(): void
    {
        Schema::table('body_types', function (Blueprint $table) {
            $table->string('image')->nullable();
        });
        Schema::table('body_types', function (Blueprint $table) {
            $table->dropColumn(['name_ka', 'name_en', 'sort']);
        });
    }

    private function keyFor(string $name): ?string
    {
        $slug = trim(preg_replace('/[^a-z0-9]+/', '_', mb_strtolower(trim($name))), '_');

        return isset(self::TYPES[$slug]) ? $slug : (self::ALIASES[$slug] ?? null);
    }

    /** Points every row that holds the old key at the new one; a discount price already set for the new key wins. */
    private function move(string $from, string $to): void
    {
        DB::table('car_models')->where('type', $from)->update(['type' => $to]);
        DB::table('packages')->where('car_type', $from)->update(['car_type' => $to]);

        $taken = DB::table('corporate_package_prices')->where('car_type', $to)->pluck('corporate_package_id');
        DB::table('corporate_package_prices')->where('car_type', $from)->whereIn('corporate_package_id', $taken)->delete();
        DB::table('corporate_package_prices')->where('car_type', $from)->update(['car_type' => $to]);
    }

    private function dropOrKeep(object $old): void
    {
        $used = DB::table('car_models')->where('type', $old->name)->exists()
            || DB::table('packages')->where('car_type', $old->name)->exists()
            || DB::table('corporate_package_prices')->where('car_type', $old->name)->exists();

        if ($used) {
            DB::table('body_types')->where('id', $old->id)->update(['name_ka' => $old->name, 'name_en' => $old->name, 'sort' => 100]);
            Log::warning('body type kept: in use and not in the new list', ['name' => $old->name]);

            return;
        }

        DB::table('body_types')->where('id', $old->id)->delete();
    }
};
