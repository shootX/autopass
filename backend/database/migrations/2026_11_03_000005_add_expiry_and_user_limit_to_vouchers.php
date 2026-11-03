<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A voucher runs until date_to (owner's rule, 2026-11-03): after it, the unsold ones leave the shop and the
     * bought ones show as expired and can't be used. Vouchers made before have none until the admin sets one.
     * user_limit: how many of it one user may buy in all (1 unless the admin raises it).
     */
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->date('date_to')->nullable()->after('price');
            $table->unsignedInteger('user_limit')->default(1)->after('count');
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn(['date_to', 'user_limit']);
        });
    }
};
