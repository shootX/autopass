<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_packages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
            $table->unsignedBigInteger('package_id');
            $table->foreign('package_id')
                ->references('id')
                ->on('packages')
                ->onDelete('cascade');
            $table->unsignedBigInteger('user_car_id');
            $table->foreign('user_car_id')
                ->references('id')
                ->on('user_cars')
                ->onDelete('cascade');
            $table->timestamp('start_date')->nullable(false)->default(now());
            $table->timestamp('end_date')->nullable(false);
            $table->integer('number_of_washes')->nullable(false);
            $table->integer('used_washes')->nullable(false)->default(0);
            $table->string('rectoken', 255)->nullable(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_packages');
    }
};
