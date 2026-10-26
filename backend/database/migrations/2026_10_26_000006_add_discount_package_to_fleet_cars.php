<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A company may have several active discount packages and picks one for each discount car.
     * Discount cars of today keep the company's one active discount package.
     */
    public function up(): void
    {
        Schema::table('fleet_cars', function (Blueprint $table) {
            $table->foreignId('corporate_package_id')->nullable()->constrained('corporate_packages')->nullOnDelete();
        });

        DB::table('corporate_packages')
            ->where('type', 'discount')
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'corporate_client_id'])
            ->each(fn ($package) => DB::table('fleet_cars')
                ->where('corporate_client_id', $package->corporate_client_id)
                ->where('mode', 'discount')
                ->update(['corporate_package_id' => $package->id]));
    }

    public function down(): void
    {
        Schema::table('fleet_cars', function (Blueprint $table) {
            $table->dropConstrainedForeignId('corporate_package_id');
        });
    }
};
