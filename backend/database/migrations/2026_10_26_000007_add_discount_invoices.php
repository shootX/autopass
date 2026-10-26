<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Besides the wash-package invoice (paid first, washes credited after), a company gets a monthly
     * invoice for its discount washes: the full price minus what the drivers paid on site. Each wash
     * points at the invoice that billed it, so no wash is billed twice.
     */
    public function up(): void
    {
        Schema::table('corporate_invoices', function (Blueprint $table) {
            $table->string('kind', 16)->default('washes');
            $table->date('period')->nullable();
            $table->json('lines')->nullable();
        });

        Schema::table('corporate_washes', function (Blueprint $table) {
            $table->foreignId('corporate_invoice_id')->nullable()->constrained('corporate_invoices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('corporate_washes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('corporate_invoice_id');
        });

        Schema::table('corporate_invoices', function (Blueprint $table) {
            $table->dropColumn(['kind', 'period', 'lines']);
        });
    }
};
