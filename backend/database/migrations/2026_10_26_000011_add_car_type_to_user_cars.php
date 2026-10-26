<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The car's own body type key (body_types.name), read from the catalog generation it was added with.
 * Empty means the model's type applies. `body_type` keeps the catalog's text as it came.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_cars', function (Blueprint $table) {
            $table->string('car_type', 32)->nullable()->after('body_type')->index();
        });
    }

    public function down(): void
    {
        Schema::table('user_cars', function (Blueprint $table) {
            $table->dropIndex(['car_type']);
            $table->dropColumn('car_type');
        });
    }
};
