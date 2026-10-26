<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The landing's editable content. One row per page (`home`, `business`, `settings`); `data` holds only
     * what the admin changed, the defaults live in resources/site/*.json. Leads come from the business form.
     */
    public function up(): void
    {
        Schema::create('site_contents', function (Blueprint $table) {
            $table->id();
            $table->string('page', 32)->unique();
            $table->json('data')->nullable();
            $table->timestamps();
        });

        Schema::create('site_leads', function (Blueprint $table) {
            $table->id();
            $table->string('company', 120);
            $table->string('phone', 32);
            $table->string('lang', 2)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_leads');
        Schema::dropIfExists('site_contents');
    }
};
