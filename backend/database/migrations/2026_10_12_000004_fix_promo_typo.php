<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** UX-09: fix the "ჭკკვიანი" typo in promo text. Idempotent; touches only that word. */
    public function up(): void
    {
        foreach (['title', 'description'] as $column) {
            DB::table('promos')
                ->where($column, 'like', '%ჭკკვიანი%')
                ->update([$column => DB::raw("REPLACE($column, 'ჭკკვიანი', 'ჭკვიანი')")]);
        }
    }

    public function down(): void
    {
        // Not reversible on purpose: restoring a typo is never wanted.
    }
};
