<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbc_payments', function (Blueprint $table) {
            $table->id();
            $table->string('merchant_payment_id', 40)->unique();
            $table->string('pay_id', 64)->nullable()->index();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('GEL');
            $table->string('status', 32)->default('pending');
            $table->json('payload');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbc_payments');
    }
};
