<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bought tickets stay when the admin removes a giveaway: tickets are soft-deleted and the key that used to
     * cascade into user_tickets refuses the delete. A package QR is unique, so a scanned code names one package.
     * On PostgreSQL a voucher or ticket price below zero is refused (NOT VALID: rows already there are left as
     * they are, new and changed rows are checked).
     */
    public function up(): void
    {
        Schema::table('tickets', fn (Blueprint $table) => $table->softDeletes());
        Schema::table('user_tickets', function (Blueprint $table) {
            $table->dropForeign(['ticket_id']);
            $table->foreign('ticket_id')->references('id')->on('tickets')->onDelete('restrict');
        });
        Schema::table('user_packages', fn (Blueprint $table) => $table->unique('qr_code'));

        if (DB::getDriverName() === 'pgsql') {
            foreach (['vouchers', 'tickets'] as $name) {
                DB::statement("ALTER TABLE {$name} ADD CONSTRAINT {$name}_price_not_negative CHECK (price >= 0) NOT VALID");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            foreach (['vouchers', 'tickets'] as $name) {
                DB::statement("ALTER TABLE {$name} DROP CONSTRAINT IF EXISTS {$name}_price_not_negative");
            }
        }

        Schema::table('user_packages', fn (Blueprint $table) => $table->dropUnique(['qr_code']));
        Schema::table('user_tickets', function (Blueprint $table) {
            $table->dropForeign(['ticket_id']);
            $table->foreign('ticket_id')->references('id')->on('tickets')->cascadeOnDelete();
        });
        Schema::table('tickets', fn (Blueprint $table) => $table->dropSoftDeletes());
    }
};
