<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A company package is either washes (stage 2) or a discount: one row per body type with the
     * full price and the percent off. Sedan 30 ₾ at 50% means the driver pays 15 ₾ on site; the
     * wash row keeps what was charged.
     */
    public function up(): void
    {
        Schema::table('corporate_packages', function (Blueprint $table) {
            $table->string('type', 16)->default('washes');
        });

        Schema::create('corporate_package_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corporate_package_id')->constrained('corporate_packages')->cascadeOnDelete();
            $table->string('car_type', 64);
            $table->decimal('full_price', 10, 2);
            $table->unsignedTinyInteger('percent');
            $table->timestamps();
            $table->unique(['corporate_package_id', 'car_type']);
        });

        Schema::table('corporate_washes', function (Blueprint $table) {
            $table->string('car_type', 64)->nullable();
            // Discount washes only: the package, its price and percent at the time and the driver's share.
            $table->foreignId('corporate_package_id')->nullable()->constrained('corporate_packages')->nullOnDelete();
            $table->decimal('full_price', 10, 2)->nullable();
            $table->unsignedTinyInteger('discount_percent')->nullable();
            $table->decimal('to_pay', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('corporate_washes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('corporate_package_id');
            $table->dropColumn(['car_type', 'full_price', 'discount_percent', 'to_pay']);
        });

        Schema::dropIfExists('corporate_package_prices');

        Schema::table('corporate_packages', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
