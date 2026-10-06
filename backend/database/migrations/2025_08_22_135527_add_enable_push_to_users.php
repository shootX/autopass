<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('enable_push_wash_appointment')->default(true);
            $table->boolean('enable_push_renewal_subscription')->default(true);
            $table->boolean('enable_push_special_promotions')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('enable_push_wash_appointment');
            $table->dropColumn('enable_push_renewal_subscription');
            $table->dropColumn('enable_push_special_promotions');
        });
    }
};
