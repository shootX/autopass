<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('body_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->unique();
            $table->string('image')->nullable();
            $table->timestamps();
        });

        Schema::table('car_models', function (Blueprint $table) {
            $table->string('type', 64)->change();
        });

        $defaults = [
            'sedan' => '/assets/img/car_types/sedan_image.png',
            'suv' => '/assets/img/car_types/suv_image.png',
            'hatchback' => '/assets/img/car_types/hatchback_image.png',
            'coupe' => '/assets/img/car_types/coupe_image.png',
            'convertible' => '/assets/img/car_types/convertible_image.png',
            'wagon' => '/assets/img/car_types/wagon_image.png',
            'pickup' => '/assets/img/car_types/pickup_image.png',
        ];

        $names = collect(array_keys($defaults))
            ->merge(DB::table('car_models')->distinct()->pluck('type'))
            ->merge(DB::table('packages')->distinct()->pluck('car_type'))
            ->filter(fn ($name) => is_string($name) && $name !== '')
            ->unique()
            ->values();

        $now = now();
        foreach ($names as $name) {
            DB::table('body_types')->insert([
                'name' => $name,
                'image' => $defaults[$name] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('body_types');

        Schema::table('car_models', function (Blueprint $table) {
            $table->string('type', 16)->change();
        });
    }
};
