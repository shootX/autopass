<?php

namespace App\Services\Security;

use App\Models\AuthGrant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ActionGrantService
{
    public function issue(User $user, string $action): string
    {
        AuthGrant::query()
            ->where('user_id', $user->id)
            ->where('action', $action)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $plain = bin2hex(random_bytes(32));

        AuthGrant::query()->create([
            'user_id' => $user->id,
            'action' => $action,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addMinutes((int) config('security.password_grant_minutes')),
        ]);

        return $plain;
    }

    public function consume(string $plain, string $action): ?User
    {
        $plain = trim($plain);
        if ($plain === '') {
            return null;
        }

        $hash = hash('sha256', $plain);

        return DB::transaction(function () use ($hash, $action) {
            $updated = AuthGrant::query()
                ->where('token_hash', $hash)
                ->where('action', $action)
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->update(['used_at' => now()]);

            if ($updated !== 1) {
                return null;
            }

            $grant = AuthGrant::query()->where('token_hash', $hash)->first();

            return $grant?->user;
        });
    }

    public function invalidate(User $user, string $action): void
    {
        AuthGrant::query()
            ->where('user_id', $user->id)
            ->where('action', $action)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);
    }
}
