<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->text('conditions')->nullable();
            $table->integer('count')->default(0);
            $table->integer('percent')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn('conditions');
            $table->dropColumn('count');
            $table->integer('percent')
                ->nullable(false)
                ->default(0)
                ->change();
        });
    }
};
