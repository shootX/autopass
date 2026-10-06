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
        Schema::create('car_washes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->nullable(false);
            $table->string('address', 255)->nullable(false);

            $table->time('work_time_start', $precision = 0)->nullable(false);
            $table->time('work_time_end', $precision = 0)->nullable(false);

            $table->string('location', 64)->nullable(false);

            $table->unsignedBigInteger('manager_id');
            $table->foreign('manager_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('car_washes');
    }
};
