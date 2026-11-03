<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the driver agreed in the app that a car they already had joins this company car. Until then a company
     * can't take a driver's own car (and the packages on it) by naming its plate and phone. A new driver phone
     * clears it.
     */
    public function up(): void
    {
        Schema::table('fleet_cars', function (Blueprint $table) {
            $table->timestamp('driver_consented_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('fleet_cars', function (Blueprint $table) {
            $table->dropColumn('driver_consented_at');
        });
    }
};
