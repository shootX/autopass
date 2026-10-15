<?php

use App\Services\Security\RetireDefaultPasswords;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Audit C1: lock admin/manager/partner accounts still using a published default password.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('password_reset_required')->default(false);
        });

        app(RetireDefaultPasswords::class)->handle();
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('password_reset_required'));
    }
};
