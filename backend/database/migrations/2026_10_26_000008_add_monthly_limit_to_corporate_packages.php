<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A discount package may cap the discount washes of each car in a calendar month; null is unlimited.
     * The cap is checked against the car's discount washes since the 1st.
     */
    public function up(): void
    {
        Schema::table('corporate_packages', function (Blueprint $table) {
            $table->unsignedSmallInteger('monthly_limit')->nullable();
        });

        Schema::table('corporate_washes', function (Blueprint $table) {
            $table->index(['fleet_car_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('corporate_washes', function (Blueprint $table) {
            $table->dropIndex(['fleet_car_id', 'created_at']);
        });

        Schema::table('corporate_packages', function (Blueprint $table) {
            $table->dropColumn('monthly_limit');
        });
    }
};
