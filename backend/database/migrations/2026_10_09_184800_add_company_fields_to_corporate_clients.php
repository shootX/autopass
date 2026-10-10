<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corporate_clients', function (Blueprint $table) {
            $table->string('legal_form', 40)->nullable();
            $table->string('identification_code', 20)->nullable()->unique();
            $table->string('legal_address')->nullable();
            $table->string('actual_address')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_code', 20)->nullable();
            $table->string('bank_account', 40)->nullable();
            $table->string('website')->nullable();
            $table->boolean('vat_payer')->default(false);
            $table->string('username', 64)->nullable()->unique();
            $table->string('password')->nullable();
            $table->boolean('credentials_custom')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('corporate_clients', function (Blueprint $table) {
            $table->dropColumn([
                'legal_form',
                'identification_code',
                'legal_address',
                'actual_address',
                'bank_name',
                'bank_code',
                'bank_account',
                'website',
                'vat_payer',
                'username',
                'password',
                'credentials_custom',
            ]);
        });
    }
};
