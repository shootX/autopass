<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** One row per approved package wash, recorded on the approving manager's branch. */
    public function up(): void
    {
        Schema::create('package_washes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_package_id')->constrained('user_packages')->cascadeOnDelete();
            $table->foreignId('car_wash_id')->constrained('car_washes')->cascadeOnDelete();
            $table->foreignId('manager_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->boolean('walk_in')->default(false);
            $table->timestamps();
            $table->index(['car_wash_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_washes');
    }
};
