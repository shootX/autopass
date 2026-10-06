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
        Schema::create('car_wash_services_lists', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('car_wash_id');
            $table->foreign('car_wash_id')
                ->references('id')
                ->on('car_washes')
                ->onDelete('cascade');
            $table->unsignedBigInteger('car_wash_service_id');
            $table->foreign('car_wash_service_id')
                ->references('id')
                ->on('car_wash_services')
                ->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('car_wash_services_lists');
    }
};
