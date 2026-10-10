<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('phone_verified_at')->nullable();
            $table->unsignedInteger('token_version')->default(0);
        });

        Schema::table('sms_temps', function (Blueprint $table) {
            $table->string('public_id', 64)->nullable()->unique();
            $table->string('code', 64)->change();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->json('context')->nullable();
        });

        Schema::create('auth_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action', 32);
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'action']);
        });

        Schema::table('verification_codes', function (Blueprint $table) {
            $table->string('code', 6)->nullable()->change();
            $table->string('code_hash', 64)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
        });

        Schema::table('corporate_clients', function (Blueprint $table) {
            $table->boolean('password_must_change')->default(false);
            $table->timestamp('temp_password_expires_at')->nullable();
            $table->unsignedInteger('session_version')->default(0);
            $table->timestamp('credentials_retired_at')->nullable();
        });

        Schema::table('partners', function (Blueprint $table) {
            $table->string('email')->nullable()->unique();
            $table->boolean('password_must_change')->default(false);
            $table->timestamp('temp_password_expires_at')->nullable();
            $table->unsignedInteger('token_version')->default(0);
        });

        Schema::table('tbc_payments', function (Blueprint $table) {
            $table->boolean('test_mode')->default(false);
            $table->string('access_hash', 64)->nullable();
            $table->timestamp('access_expires_at')->nullable();
        });

        app(\App\Services\Security\RetireGuessablePasswords::class)->handle();
    }

    public function down(): void
    {
        Schema::table('tbc_payments', function (Blueprint $table) {
            $table->dropColumn(['test_mode', 'access_hash', 'access_expires_at']);
        });

        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn(['email', 'password_must_change', 'temp_password_expires_at', 'token_version']);
        });

        Schema::table('corporate_clients', function (Blueprint $table) {
            $table->dropColumn(['password_must_change', 'temp_password_expires_at', 'session_version', 'credentials_retired_at']);
        });

        Schema::table('verification_codes', function (Blueprint $table) {
            $table->dropColumn(['code_hash', 'attempts', 'expires_at', 'consumed_at']);
        });

        Schema::dropIfExists('auth_grants');

        Schema::table('sms_temps', function (Blueprint $table) {
            $table->dropUnique(['public_id']);
            $table->dropColumn(['public_id', 'attempts', 'expires_at', 'consumed_at', 'context']);
            $table->string('code', 6)->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone_verified_at', 'token_version']);
        });
    }
};
