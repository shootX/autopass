<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The body type a company named for its car. It is confirmed only when geoapi.ge lists it for that model
     * and year; otherwise the car waits (choose or unknown) and this type is the hint a re-check confirms once
     * geoapi.ge lists it, and what the admin sees when confirming by hand.
     */
    public function up(): void
    {
        Schema::table('fleet_cars', function (Blueprint $table) {
            $table->string('body_requested', 32)->nullable()->after('body_source');
        });
    }

    public function down(): void
    {
        Schema::table('fleet_cars', function (Blueprint $table) {
            $table->dropColumn('body_requested');
        });
    }
};
