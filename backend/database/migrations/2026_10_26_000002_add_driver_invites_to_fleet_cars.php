<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A fleet car is tied to a driver by phone: invited (SMS sent, driver not signed in yet),
     * active (driver signed in, car linked to their account) or blocked. Existing cars have
     * no phone yet, so they start as invited and the company adds the phone.
     */
    public function up(): void
    {
        Schema::table('fleet_cars', function (Blueprint $table) {
            $table->string('status', 16)->default('invited');
            $table->string('driver_phone', 20)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('user_car_id')->nullable()->constrained('user_cars')->nullOnDelete();
            $table->string('activation_error', 32)->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->index(['driver_phone', 'status']);
            $table->index('user_car_id');
        });
    }

    public function down(): void
    {
        Schema::table('fleet_cars', function (Blueprint $table) {
            $table->dropIndex(['driver_phone', 'status']);
            $table->dropIndex(['user_car_id']);
            $table->dropConstrainedForeignId('user_id');
            $table->dropConstrainedForeignId('user_car_id');
            $table->dropColumn(['status', 'driver_phone', 'activation_error', 'invited_at', 'activated_at', 'blocked_at']);
        });
    }
};
