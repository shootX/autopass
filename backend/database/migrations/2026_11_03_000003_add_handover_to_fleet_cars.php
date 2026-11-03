<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The app car of the driver a company replaced. It leaves that driver's app when the phone changes and goes,
     * with its running packages, to the next driver the first time they sign in (owner's rule, 2026-11-03).
     */
    public function up(): void
    {
        Schema::table('fleet_cars', function (Blueprint $table) {
            $table->unsignedBigInteger('handover_user_car_id')->nullable()->after('user_car_id');
        });
    }

    public function down(): void
    {
        Schema::table('fleet_cars', function (Blueprint $table) {
            $table->dropColumn('handover_user_car_id');
        });
    }
};
