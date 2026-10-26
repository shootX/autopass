<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A company car's year and body type, as the company gave them, and the catalog model they came from:
     * the driver's app car is made from these when the driver signs in.
     */
    public function up(): void
    {
        Schema::table('fleet_cars', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->nullable()->after('model');
            $table->string('car_type', 32)->nullable()->after('year');
            $table->string('catalog_source', 16)->nullable()->after('car_type');
            $table->unsignedBigInteger('catalog_brand_id')->nullable()->after('catalog_source');
            $table->unsignedBigInteger('catalog_model_id')->nullable()->after('catalog_brand_id');
            $table->unsignedBigInteger('generation_id')->nullable()->after('catalog_model_id');
        });
    }

    public function down(): void
    {
        Schema::table('fleet_cars', function (Blueprint $table) {
            $table->dropColumn(['year', 'car_type', 'catalog_source', 'catalog_brand_id', 'catalog_model_id', 'generation_id']);
        });
    }
};
