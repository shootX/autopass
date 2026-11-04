<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A wash-package invoice keeps the body type it was issued for: paying it credits that type's pool even if the
 * admin later edits or deletes the package (QA audit 2026-11-04, QA-01/QA-02).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corporate_invoices', function (Blueprint $table) {
            $table->string('car_type', 32)->nullable()->after('corporate_package_id');
        });

        // Invoices issued before: the type their package has now (the best record there is).
        DB::table('corporate_invoices')->whereNull('car_type')->whereNotNull('corporate_package_id')
            ->orderBy('id')->get(['id', 'corporate_package_id'])
            ->each(function ($invoice) {
                $type = DB::table('corporate_packages')->where('id', $invoice->corporate_package_id)->value('car_type');
                if ($type !== null) {
                    DB::table('corporate_invoices')->where('id', $invoice->id)->update(['car_type' => $type]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('corporate_invoices', function (Blueprint $table) {
            $table->dropColumn('car_type');
        });
    }
};
