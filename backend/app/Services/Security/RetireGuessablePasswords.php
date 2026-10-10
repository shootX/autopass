<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RetireGuessablePasswords
{
    public function handle(): void
    {
        $known = '34rbb45i43jnv8493uh';

        DB::table('users')
            ->where('role', 0)
            ->whereNotNull('password')
            ->orderBy('id')
            ->select(['id', 'password', 'token_version'])
            ->each(function ($row) use ($known) {
                if (! is_string($row->password) || ! Hash::check($known, $row->password)) {
                    return;
                }

                DB::table('users')->where('id', $row->id)->update([
                    'password' => Hash::make(Str::random(40)),
                    'token_version' => ((int) $row->token_version) + 1,
                ]);
            });

        if (! DB::getSchemaBuilder()->hasColumn('corporate_clients', 'credentials_custom')) {
            return;
        }

        DB::table('corporate_clients')
            ->where('credentials_custom', false)
            ->whereNotNull('password')
            ->orderBy('id')
            ->select(['id', 'session_version'])
            ->each(function ($row) {
                DB::table('corporate_clients')->where('id', $row->id)->update([
                    'password' => Hash::make(Str::random(40)),
                    'password_must_change' => true,
                    'temp_password_expires_at' => now()->subMinute(),
                    'session_version' => ((int) $row->session_version) + 1,
                    'credentials_retired_at' => now(),
                ]);
            });
    }
}
