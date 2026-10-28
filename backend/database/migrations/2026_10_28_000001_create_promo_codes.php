<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A code is either a percent off a points purchase (discount) or free points on activation (points).
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('kind', 16);
            $table->unsignedInteger('value');
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // One use per user and code; a discount use is written when its payment succeeds.
        Schema::create('promo_code_uses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promo_code_id')->constrained('promo_codes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('tbc_payment_id')->nullable();
            $table->unsignedInteger('points')->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->timestamps();
            $table->unique(['promo_code_id', 'user_id']);
        });

        // The driver (or the admin) can switch a package's renewal off; the card token stays for a switch back on.
        Schema::table('user_packages', function (Blueprint $table) {
            $table->boolean('auto_renew')->default(true);
        });

        // Points given to an inviter for a friend's paid package, once per payment.
        Schema::table('tbc_payments', function (Blueprint $table) {
            $table->boolean('referral_paid')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('tbc_payments', fn (Blueprint $table) => $table->dropColumn('referral_paid'));
        Schema::table('user_packages', fn (Blueprint $table) => $table->dropColumn('auto_renew'));
        Schema::dropIfExists('promo_code_uses');
        Schema::dropIfExists('promo_codes');
    }
};
