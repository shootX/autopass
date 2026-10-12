<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ties each voucher to the partner that may redeem it.
     * Nullable on purpose: existing vouchers are backfilled by an admin
     * (see docs/MEMORY.md). Unassigned vouchers cannot be redeemed.
     */
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->unsignedBigInteger('partner_id')->nullable()->after('category_id');
            $table->foreign('partner_id')->references('id')->on('partners')->nullOnDelete();
            $table->index('partner_id');
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropForeign(['partner_id']);
            $table->dropIndex(['partner_id']);
            $table->dropColumn('partner_id');
        });
    }
};
