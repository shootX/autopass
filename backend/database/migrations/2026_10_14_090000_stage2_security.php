<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pending_email')->nullable();
        });
        Schema::table('partners', function (Blueprint $table) {
            $table->boolean('ban')->default(false);
        });
        Schema::table('corporate_clients', function (Blueprint $table) {
            $table->boolean('ban')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('pending_email'));
        Schema::table('partners', fn (Blueprint $table) => $table->dropColumn('ban'));
        Schema::table('corporate_clients', fn (Blueprint $table) => $table->dropColumn('ban'));
    }
};
