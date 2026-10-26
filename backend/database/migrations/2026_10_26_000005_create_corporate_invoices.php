<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A company orders a wash package and gets an invoice; once the admin marks it paid, the
     * package's washes are credited. Buyer and seller details are copied in when the invoice is
     * issued, so a later change of the company's details leaves past invoices as they were.
     */
    public function up(): void
    {
        Schema::create('corporate_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corporate_client_id')->nullable()->constrained('corporate_clients')->nullOnDelete();
            $table->foreignId('corporate_package_id')->nullable()->constrained('corporate_packages')->nullOnDelete();
            $table->string('number', 32)->nullable()->unique();
            $table->string('title', 120);
            $table->unsignedInteger('washes');
            $table->decimal('amount', 10, 2);
            $table->unsignedTinyInteger('vat_rate')->default(0);
            // issued, paid or cancelled.
            $table->string('status', 16)->default('issued');
            // company or admin.
            $table->string('ordered_by', 16);
            $table->json('buyer');
            $table->json('seller');
            $table->date('due_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('paid_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['corporate_client_id', 'id']);
            $table->index(['status', 'id']);
        });

        Schema::table('corporate_wash_moves', function (Blueprint $table) {
            $table->foreignId('corporate_invoice_id')->nullable()->constrained('corporate_invoices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('corporate_wash_moves', function (Blueprint $table) {
            $table->dropConstrainedForeignId('corporate_invoice_id');
        });

        Schema::dropIfExists('corporate_invoices');
    }
};
