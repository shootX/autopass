<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Additive only: existing brands/models keep their ids and become source=local; user_cars untouched except new nullable year.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('car_brands', function (Blueprint $table) {
            $table->string('source', 16)->default('local');
            $table->unsignedBigInteger('external_brand_id')->nullable();
            $table->unique(['source', 'external_brand_id']);
        });
        Schema::table('car_models', function (Blueprint $table) {
            $table->string('source', 16)->default('local');
            $table->unsignedBigInteger('external_model_id')->nullable();
            $table->unique(['source', 'external_model_id']);
        });
        Schema::table('user_cars', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('user_cars', fn (Blueprint $table) => $table->dropColumn('year'));
        Schema::table('car_models', function (Blueprint $table) {
            $table->dropUnique(['source', 'external_model_id']);
            $table->dropColumn(['source', 'external_model_id']);
        });
        Schema::table('car_brands', function (Blueprint $table) {
            $table->dropUnique(['source', 'external_brand_id']);
            $table->dropColumn(['source', 'external_brand_id']);
        });
    }
};
