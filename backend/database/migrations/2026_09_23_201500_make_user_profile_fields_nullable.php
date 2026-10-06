<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE users ALTER COLUMN surname DROP NOT NULL');
        DB::statement('ALTER TABLE users ALTER COLUMN date_of_birth DROP NOT NULL');
        DB::statement('ALTER TABLE users ALTER COLUMN sex DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users ALTER COLUMN surname SET NOT NULL');
        DB::statement('ALTER TABLE users ALTER COLUMN date_of_birth SET NOT NULL');
        DB::statement('ALTER TABLE users ALTER COLUMN sex SET NOT NULL');
    }
};
