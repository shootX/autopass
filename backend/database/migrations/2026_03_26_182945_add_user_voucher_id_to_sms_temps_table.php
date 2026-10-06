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
        Schema::table('sms_temps', function (Blueprint $table) {
            $table->unsignedBigInteger('user_voucher_id')->nullable();
            $table->foreign('user_voucher_id')
                ->references('id')
                ->on('user_vouchers')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sms_temps', function (Blueprint $table) {
            $table->dropForeign(['user_voucher_id']);
            $table->dropColumn('user_voucher_id');
        });
    }
};
