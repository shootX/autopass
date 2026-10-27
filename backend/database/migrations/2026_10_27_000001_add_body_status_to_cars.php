<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether a car's body type is confirmed. confirmed: car_type is the type (geoapi.ge named one type for the car,
     * or the driver, the company or an admin picked one of those it named); choose: geoapi.ge named several, kept in
     * body_options, and the driver picks one; unknown: geoapi.ge could not tell. Packages, washes and QR codes need
     * a confirmed type.
     *
     * Nothing is confirmed here: this migration asks no catalog. Every car that exists becomes unknown and
     * unchecked (body_checked_at null) with its old car_type kept, and `php artisan cars:check-bodies` checks them
     * against geoapi.ge (a report first, writes only with --apply). Packages bought before keep working until they
     * end (user_packages.body_legacy), so no paid package stops at the wash.
     */
    public function up(): void
    {
        foreach (['user_cars', 'fleet_cars'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('body_status', 16)->default('unknown')->after('car_type');
                $table->json('body_options')->nullable()->after('body_status');
                $table->boolean('body_incomplete')->default(false)->after('body_options');
                $table->string('body_source', 16)->nullable()->after('body_incomplete');
                $table->timestamp('body_checked_at')->nullable()->after('body_source');
            });
        }

        Schema::table('user_packages', function (Blueprint $table) {
            $table->boolean('body_legacy')->default(false)->after('qr_code');
        });
        DB::table('user_packages')->update(['body_legacy' => true]);
    }

    public function down(): void
    {
        Schema::table('user_packages', function (Blueprint $table) {
            $table->dropColumn('body_legacy');
        });
        foreach (['user_cars', 'fleet_cars'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn(['body_status', 'body_options', 'body_incomplete', 'body_source', 'body_checked_at']);
            });
        }
    }
};
