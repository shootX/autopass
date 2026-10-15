<?php

namespace App\Services\Security;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Staff and partner accounts still on a password that was published in the public git history
 * are locked: the password is replaced with random bytes, every token/session is revoked and
 * login stays blocked until the owner sets a new password through the SMS reset flow
 * (partners: until an admin issues a temporary password).
 */
class RetireDefaultPasswords
{
    /** Published in commit d14001c (seeders and AuthController). */
    public const KNOWN = ['admin123', 'manager123', 'partner123', 'client123', '34rbb45i43jnv8493uh'];

    public function handle(): array
    {
        $locked = ['users' => 0, 'partners' => 0];

        DB::table('users')
            ->whereIn('role', [User::ROLE_MANAGER, User::ROLE_ADMIN])
            ->whereNotNull('password')
            ->orderBy('id')
            ->select(['id', 'password', 'token_version'])
            ->each(function ($row) use (&$locked) {
                if (! $this->isKnown($row->password)) {
                    return;
                }
                DB::table('users')->where('id', $row->id)->update([
                    'password' => Hash::make(Str::random(40)),
                    'password_reset_required' => true,
                    'token_version' => (int) $row->token_version + 1,
                ]);
                if (Schema::hasTable('sessions')) {
                    DB::table('sessions')->where('user_id', $row->id)->delete();
                }
                $locked['users']++;
            });

        DB::table('partners')
            ->whereNotNull('password')
            ->orderBy('id')
            ->select(['id', 'password', 'token_version'])
            ->each(function ($row) use (&$locked) {
                if (! $this->isKnown($row->password)) {
                    return;
                }
                DB::table('partners')->where('id', $row->id)->update([
                    'password' => Hash::make(Str::random(40)),
                    'password_must_change' => true,
                    'temp_password_expires_at' => now()->subMinute(),
                    'token_version' => (int) $row->token_version + 1,
                ]);
                $locked['partners']++;
            });

        return $locked;
    }

    private function isKnown(mixed $hash): bool
    {
        if (! is_string($hash) || $hash === '') {
            return false;
        }
        foreach (self::KNOWN as $plain) {
            try {
                if (Hash::check($plain, $hash)) {
                    return true;
                }
            } catch (\RuntimeException) {
                // Non-bcrypt legacy hash: can't be one of ours.
                return false;
            }
        }

        return false;
    }
}
