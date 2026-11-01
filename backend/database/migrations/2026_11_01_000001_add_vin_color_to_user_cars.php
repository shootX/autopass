<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Read from the tech passport (E and R) when the driver scans it; the photo itself is never sent or kept.
        Schema::table('user_cars', function (Blueprint $table) {
            $table->string('vin', 17)->nullable();
            $table->string('color', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('user_cars', function (Blueprint $table) {
            $table->dropColumn(['vin', 'color']);
        });
    }
};
