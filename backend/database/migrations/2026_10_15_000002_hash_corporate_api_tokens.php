<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Audit M7: corporate API keys are stored as sha256 only. Existing keys keep working
// (hashed in place), the plaintext column is emptied. Audit M5: wider audit action names.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corporate_clients', function (Blueprint $table) {
            $table->string('api_token_hash', 64)->nullable()->unique();
            $table->string('api_token_last4', 4)->nullable();
            $table->timestamp('api_token_used_at')->nullable();
        });
        Schema::table('corporate_clients', function (Blueprint $table) {
            $table->string('api_token', 64)->nullable()->change();
        });

        DB::table('corporate_clients')->whereNotNull('api_token')->orderBy('id')->select(['id', 'api_token'])
            ->each(function ($row) {
                DB::table('corporate_clients')->where('id', $row->id)->update([
                    'api_token_hash' => hash('sha256', (string) $row->api_token),
                    'api_token_last4' => substr((string) $row->api_token, -4),
                    'api_token' => null,
                ]);
            });

        Schema::table('admin_audit_logs', function (Blueprint $table) {
            $table->string('action', 96)->change();
        });
    }

    public function down(): void
    {
        // Plaintext keys can't be restored; clients must be issued a new key after a rollback.
        Schema::table('corporate_clients', function (Blueprint $table) {
            $table->dropUnique(['api_token_hash']);
            $table->dropColumn(['api_token_hash', 'api_token_last4', 'api_token_used_at']);
        });
    }
};
