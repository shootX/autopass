<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A company's washes are kept per body type: a wash package is bought for one type, and a car washes
     * only from its own type's pool. corporate_clients.washes_balance stays the sum of the pools.
     */
    public function up(): void
    {
        Schema::create('corporate_wash_pools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corporate_client_id')->constrained('corporate_clients')->cascadeOnDelete();
            $table->string('car_type', 32);
            // Washes of this type the company owns, the ones given to its cars included.
            $table->integer('balance')->default(0);
            $table->timestamps();
            $table->unique(['corporate_client_id', 'car_type']);
        });

        Schema::table('corporate_packages', function (Blueprint $table) {
            // Wash packages only: the body type its washes are for.
            $table->string('car_type', 32)->nullable()->after('type');
        });

        Schema::table('corporate_wash_moves', function (Blueprint $table) {
            $table->string('car_type', 32)->nullable()->after('type');
        });

        $this->backfill();
    }

    /**
     * Every company car gets a body type (its app car's, else its model's), and the washes a company already
     * has are split: each car's share goes to its type's pool, the free rest to the type most of its cars have.
     * That type is also given to the company's wash packages.
     */
    private function backfill(): void
    {
        $modelTypes = DB::table('car_models')->join('car_brands', 'car_brands.id', '=', 'car_models.brand_id')
            ->get(['car_brands.name as brand', 'car_models.name as model', 'car_models.type'])
            ->mapWithKeys(fn ($row) => [mb_strtolower($row->brand).'|'.mb_strtolower($row->model) => $row->type]);
        $fallback = DB::table('body_types')->where('name', 'sedan')->value('name') ?? DB::table('body_types')->orderBy('id')->value('name') ?? 'sedan';

        $cars = DB::table('fleet_cars')
            ->leftJoin('user_cars', 'user_cars.id', '=', 'fleet_cars.user_car_id')
            ->leftJoin('car_models', 'car_models.id', '=', 'user_cars.model_id')
            ->get(['fleet_cars.id', 'fleet_cars.corporate_client_id', 'fleet_cars.brand', 'fleet_cars.model', 'fleet_cars.car_type',
                'fleet_cars.washes_left', 'user_cars.car_type as own_type', 'car_models.type as model_type']);
        foreach ($cars as $car) {
            $type = $car->car_type ?: $car->own_type ?: $car->model_type
                ?: ($modelTypes[mb_strtolower((string) $car->brand).'|'.mb_strtolower((string) $car->model)] ?? null);
            $car->car_type = $type;
            if ($type !== null) {
                DB::table('fleet_cars')->where('id', $car->id)->update(['car_type' => $type]);
            }
        }

        foreach (DB::table('corporate_clients')->get(['id', 'washes_balance']) as $client) {
            $own = $cars->where('corporate_client_id', $client->id);
            $common = $own->whereNotNull('car_type')->countBy('car_type')->sortDesc()->keys()->first() ?? $fallback;
            $pools = [];
            foreach ($own as $car) {
                if ((int) $car->washes_left > 0) {
                    $type = $car->car_type ?? $common;
                    if ($car->car_type === null) {
                        DB::table('fleet_cars')->where('id', $car->id)->update(['car_type' => $type]);
                    }
                    $pools[$type] = ($pools[$type] ?? 0) + (int) $car->washes_left;
                }
            }
            $free = (int) $client->washes_balance - array_sum($pools);
            if ($free > 0) {
                $pools[$common] = ($pools[$common] ?? 0) + $free;
            }
            // Wash packages made before had no body type: the company's usual one, which the admin can change.
            DB::table('corporate_packages')->where('corporate_client_id', $client->id)->where('type', 'washes')
                ->whereNull('car_type')->update(['car_type' => $common]);
            foreach ($pools as $type => $balance) {
                DB::table('corporate_wash_pools')->insert([
                    'corporate_client_id' => $client->id, 'car_type' => $type, 'balance' => $balance,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('corporate_wash_moves', function (Blueprint $table) {
            $table->dropColumn('car_type');
        });
        Schema::table('corporate_packages', function (Blueprint $table) {
            $table->dropColumn('car_type');
        });
        Schema::dropIfExists('corporate_wash_pools');
    }
};
