<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** BE-19: mark vouchers as used instead of deleting them. */
    public function up(): void
    {
        Schema::table('user_vouchers', function (Blueprint $table) {
            $table->timestamp('used_at')->nullable();
            $table->unsignedBigInteger('used_by_partner_id')->nullable();
            $table->foreign('used_by_partner_id')->references('id')->on('partners')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_vouchers', function (Blueprint $table) {
            $table->dropForeign(['used_by_partner_id']);
            $table->dropColumn(['used_at', 'used_by_partner_id']);
        });
    }
};
