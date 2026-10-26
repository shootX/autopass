<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A company buys washes by invoice and the admin credits them to its pool. The company hands
     * them out to its cars: a pool of 100 with 4 on car X becomes 99 and 3 after one wash. Every
     * change of the pool or of a car is a row in corporate_wash_moves.
     */
    public function up(): void
    {
        Schema::create('corporate_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corporate_client_id')->constrained('corporate_clients')->cascadeOnDelete();
            $table->string('title', 120);
            $table->unsignedInteger('washes');
            $table->decimal('price', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('corporate_clients', function (Blueprint $table) {
            // The whole pool, washes already given to cars included.
            $table->integer('washes_balance')->default(0);
        });

        Schema::table('fleet_cars', function (Blueprint $table) {
            $table->string('mode', 16)->default('washes');
            $table->integer('washes_left')->default(0);
            $table->string('qr_code', 32)->nullable()->unique();
            $table->timestamp('qr_expires_at')->nullable();
        });

        Schema::create('corporate_washes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corporate_client_id')->constrained('corporate_clients')->cascadeOnDelete();
            $table->foreignId('fleet_car_id')->nullable()->constrained('fleet_cars')->nullOnDelete();
            $table->string('plate', 32);
            $table->string('mode', 16);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('car_wash_id')->nullable()->constrained('car_washes')->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->boolean('walk_in')->default(false);
            $table->timestamps();
            $table->index(['corporate_client_id', 'created_at']);
            $table->index(['car_wash_id', 'created_at']);
        });

        Schema::create('corporate_wash_moves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corporate_client_id')->constrained('corporate_clients')->cascadeOnDelete();
            // credit, correction and wash change the pool; allocate changes one car only.
            $table->string('type', 16);
            $table->integer('amount');
            $table->integer('balance_after');
            $table->foreignId('fleet_car_id')->nullable()->constrained('fleet_cars')->nullOnDelete();
            $table->string('plate', 32)->nullable();
            $table->integer('car_balance_after')->nullable();
            $table->foreignId('corporate_package_id')->nullable()->constrained('corporate_packages')->nullOnDelete();
            $table->foreignId('corporate_wash_id')->nullable()->constrained('corporate_washes')->nullOnDelete();
            // admin, company, api or manager; actor_id is the admin or manager user.
            $table->string('actor', 16);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->index(['corporate_client_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corporate_wash_moves');
        Schema::dropIfExists('corporate_washes');

        Schema::table('fleet_cars', function (Blueprint $table) {
            $table->dropUnique(['qr_code']);
            $table->dropColumn(['mode', 'washes_left', 'qr_code', 'qr_expires_at']);
        });

        Schema::table('corporate_clients', function (Blueprint $table) {
            $table->dropColumn('washes_balance');
        });

        Schema::dropIfExists('corporate_packages');
    }
};
