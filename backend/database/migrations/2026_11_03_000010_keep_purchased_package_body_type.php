<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_packages', fn (Blueprint $table) => $table->string('body_type', 64)->nullable());
        // The catalogue is the only historical category available for older purchases. New checkouts snapshot
        // their category before payment, so later catalogue edits cannot change the purchased terms.
        DB::table('user_packages')->update(['body_type' => DB::raw('(select car_type from packages where packages.id = user_packages.package_id)')]);
    }

    public function down(): void
    {
        Schema::table('user_packages', fn (Blueprint $table) => $table->dropColumn('body_type'));
    }
};
