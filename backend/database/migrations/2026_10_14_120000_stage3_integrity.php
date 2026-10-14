<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $type = DB::selectOne("SELECT data_type FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'appointments' AND column_name = 'approved'")?->data_type;
            if ($type === 'boolean') {
                DB::statement('ALTER TABLE appointments ALTER COLUMN approved DROP DEFAULT');
                DB::statement('ALTER TABLE appointments ALTER COLUMN approved TYPE smallint USING CASE WHEN approved THEN 1 ELSE 0 END');
                DB::statement('ALTER TABLE appointments ALTER COLUMN approved SET DEFAULT 0');
            }
        }
        // Validate rather than silently changing unknown legacy statuses.
        if (DB::table('appointments')->whereNotIn('approved', [0, 1, 2, 3, 4, 5])->exists()) {
            throw new RuntimeException('Unknown appointment statuses: review legacy data before migration');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE appointments ADD CONSTRAINT appointments_status_valid CHECK (approved BETWEEN 0 AND 5)');
        }
        Schema::table('user_packages', function (Blueprint $table) {
            $table->foreignId('tbc_payment_id')->nullable()->unique()->constrained('tbc_payments')->nullOnDelete();
            $table->timestamp('expiration_notified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('user_packages', function (Blueprint $table) {
            $table->dropUnique(['tbc_payment_id']);
            $table->dropConstrainedForeignId('tbc_payment_id');
            $table->dropColumn('expiration_notified_at');
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE appointments DROP CONSTRAINT IF EXISTS appointments_status_valid');
        }
        // Intentionally keep the integer status type: converting history back to boolean would lose data.
    }
};
