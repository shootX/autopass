<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('corporate_clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('note')->nullable();
            $table->string('api_token', 64)->unique();
            $table->timestamps();
        });

        Schema::create('fleet_cars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corporate_client_id')->constrained('corporate_clients')->cascadeOnDelete();
            $table->string('plate', 32);
            $table->string('brand', 80);
            $table->string('model', 80);
            $table->string('source', 16)->default('manual');
            $table->timestamps();
            $table->unique('plate');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_cars');
        Schema::dropIfExists('corporate_clients');
    }
};
