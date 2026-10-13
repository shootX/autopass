<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_dispatches', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->string('purpose', 32);
            $table->string('phone_mask', 32);
            $table->string('status', 16);
            $table->integer('error_code')->nullable();
            $table->text('body_encrypted')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_dispatches');
    }
};
