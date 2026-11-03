<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('fleet_cars')->where('body_source', 'company')->orderBy('id')->chunkById(100, function ($cars) {
            foreach ($cars as $car) {
                $types = json_decode((string) $car->body_options, true) ?? [];
                if (count($types) === 1 && ! $car->body_incomplete) {
                    continue;
                }
                $userCar = $car->user_car_id ? DB::table('user_cars')->where('id', $car->user_car_id)->first() : null;
                if ($userCar && $userCar->body_status === 'confirmed'
                    && in_array($userCar->body_source, ['driver', 'admin', 'catalog'], true)
                    && $userCar->car_type === $car->car_type) {
                    DB::table('fleet_cars')->where('id', $car->id)->update(['body_source' => $userCar->body_source]);

                    continue;
                }
                $status = $types === [] ? 'unknown' : 'choose';
                // Keep car_type for returning already allocated washes to their original pool.
                DB::table('fleet_cars')->where('id', $car->id)->update([
                    'body_status' => $status, 'body_source' => null, 'corporate_package_id' => null,
                    'qr_code' => null, 'qr_expires_at' => null,
                ]);
                if ($car->user_car_id) {
                    $changed = DB::table('user_cars')->where('id', $car->user_car_id)
                        ->update(['body_status' => $status, 'body_source' => null, 'car_type' => null]);
                    if ($changed) {
                        DB::table('user_packages')->where('user_car_id', $car->user_car_id)
                            ->update(['qr_expires_at' => null, 'body_legacy' => false]);
                    }
                }
            }
        });
    }

    public function down(): void
    {
        // Never manufacture a driver's confirmation on rollback.
    }
};
