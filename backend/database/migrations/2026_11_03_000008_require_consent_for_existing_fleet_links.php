<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fleet_cars', function (Blueprint $table) {
            $table->boolean('driver_consent_required')->default(false);
        });
        // Preserve cars, balances, packages and owners. Old links without a recorded yes must be answered
        // before a company can move them; declining only removes the company link, never the driver's car.
        DB::table('fleet_cars')->whereNotNull('user_car_id')->whereNull('driver_consented_at')->update([
            'driver_consent_required' => true, 'activation_error' => 'driver_consent', 'qr_code' => null, 'qr_expires_at' => null,
        ]);
    }

    public function down(): void
    {
        Schema::table('fleet_cars', fn (Blueprint $table) => $table->dropColumn('driver_consent_required'));
    }
};
