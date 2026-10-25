<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Additive only: generation and modification picked from the remote catalog (geoapi.ge ids), all nullable.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_cars', function (Blueprint $table) {
            $table->unsignedBigInteger('generation_id')->nullable();
            $table->string('generation_name', 128)->nullable();
            $table->unsignedBigInteger('modification_id')->nullable();
            $table->string('modification_name', 128)->nullable();
            $table->string('body_type', 32)->nullable();
            $table->string('photo', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('user_cars', fn (Blueprint $table) => $table->dropColumn([
            'generation_id', 'generation_name', 'modification_id', 'modification_name', 'body_type', 'photo',
        ]));
    }
};
