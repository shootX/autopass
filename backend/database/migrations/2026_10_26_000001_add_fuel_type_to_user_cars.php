<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Additive: the fuel the owner picked (UserCar::FUEL_TYPES code), nullable for existing cars.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_cars', function (Blueprint $table) {
            $table->string('fuel_type', 16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('user_cars', fn (Blueprint $table) => $table->dropColumn('fuel_type'));
    }
};
