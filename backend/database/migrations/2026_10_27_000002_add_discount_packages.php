<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An app package is either washes (as before) or a discount: 30 days at a percent off the full
     * price of one body type, unlimited (count_washes 0) or up to count_washes washes. The driver pays
     * the rest on site; the bought package and each wash keep the price and percent of the time.
     */
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->string('type', 16)->default('washes');
            $table->decimal('full_price', 10, 2)->nullable();
            $table->unsignedTinyInteger('percent')->nullable();
        });

        Schema::table('user_packages', function (Blueprint $table) {
            $table->boolean('unlimited')->default(false);
            $table->decimal('full_price', 10, 2)->nullable();
            $table->unsignedTinyInteger('discount_percent')->nullable();
        });

        Schema::table('package_washes', function (Blueprint $table) {
            $table->decimal('full_price', 10, 2)->nullable();
            $table->unsignedTinyInteger('discount_percent')->nullable();
            $table->decimal('to_pay', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('package_washes', function (Blueprint $table) {
            $table->dropColumn(['full_price', 'discount_percent', 'to_pay']);
        });
        Schema::table('user_packages', function (Blueprint $table) {
            $table->dropColumn(['unlimited', 'full_price', 'discount_percent']);
        });
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['type', 'full_price', 'percent']);
        });
    }
};
