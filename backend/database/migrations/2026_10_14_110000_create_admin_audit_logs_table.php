<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id')->nullable()->index();
            $table->string('target_type', 32);
            $table->unsignedBigInteger('target_id');
            $table->string('action', 32);
            $table->string('field', 32)->nullable();
            $table->string('old_value', 255)->nullable();
            $table->string('new_value', 255)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
    }
};
