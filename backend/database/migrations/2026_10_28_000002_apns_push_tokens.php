<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pushes go straight to Apple now. Rows from before are OneSignal subscription ids and are never sent to.
        Schema::table('user_push_tokens', function (Blueprint $table) {
            $table->string('push_id', 255)->change();
            $table->string('kind', 16)->default('onesignal');
            $table->boolean('sandbox')->default(false);
            $table->string('lang', 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('user_push_tokens', function (Blueprint $table) {
            $table->dropColumn(['kind', 'sandbox', 'lang']);
        });
    }
};
