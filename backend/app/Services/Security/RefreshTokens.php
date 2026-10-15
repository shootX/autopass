<?php

namespace App\Services\Security;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Rotating refresh tokens for the SPA, carried in an httpOnly cookie scoped to /api/refresh.
 *
 * - Every login starts a family; each refresh marks the presented token used and issues the next.
 * - A used token presented again within security.refresh_reuse_grace_seconds (30) is a second
 *   tab racing the first: it gets a fresh token in the same family. Later reuse, or reuse of a
 *   revoked token, revokes the whole family (theft signal) → 401.
 * - A family never outlives security.session_max_minutes from its login.
 * - A token_version bump (password change, logout everywhere, ban flow) kills old families.
 */
class RefreshTokens
{
    public const COOKIE = 'ap_refresh';

    public const PATH = '/api/refresh';

    /** @return array{0: string, 1: string, 2: Cookie} plain token, family, cookie */
    public function start(User $user, ?string $family = null, ?int $sessionStartedAt = null): array
    {
        $family ??= bin2hex(random_bytes(16));
        $started = Carbon::createFromTimestamp($sessionStartedAt ?? now()->timestamp);
        $expires = $this->expiry($started);
        $plain = bin2hex(random_bytes(32));

        DB::table('refresh_tokens')->insert([
            'user_id' => $user->id,
            'family' => $family,
            'token_hash' => hash('sha256', $plain),
            'token_version' => (int) $user->token_version,
            'session_started_at' => $started,
            'expires_at' => $expires,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$plain, $family, $this->cookie($plain, $expires)];
    }

    /**
     * Consumes a presented token. Returns [user, family, session start] for the caller to issue
     * the next pair, or null (unknown, reused, expired, session over, user no longer valid).
     *
     * @return array{0: User, 1: string, 2: int}|null
     */
    public function consume(string $plain): ?array
    {
        if ($plain === '' || strlen($plain) > 128) {
            return null;
        }

        return DB::transaction(function () use ($plain) {
            $row = DB::table('refresh_tokens')->where('token_hash', hash('sha256', $plain))->lockForUpdate()->first();
            if (! $row) {
                return null;
            }
            $grace = (int) config('security.refresh_reuse_grace_seconds', 30);
            $racing = $row->revoked_at === null && $row->used_at !== null
                && Carbon::parse($row->used_at)->gt(now()->subSeconds($grace));
            if (! $racing && ($row->used_at !== null || $row->revoked_at !== null)) {
                // Reuse of a rotated token: someone else may hold the newer one. End the session.
                $this->revokeFamily($row->family);

                return null;
            }

            $user = User::query()->find($row->user_id);
            $started = Carbon::parse($row->session_started_at);
            $over = $started->copy()->addMinutes((int) config('security.session_max_minutes', 43200))->lte(now());
            if (! $user || $user->ban || $user->password_reset_required
                || (int) $user->token_version !== (int) $row->token_version
                || Carbon::parse($row->expires_at)->lte(now()) || $over) {
                $this->revokeFamily($row->family);

                return null;
            }

            if (! $racing) {
                // The first use starts the grace window; a racing reuse doesn't extend it.
                DB::table('refresh_tokens')->where('id', $row->id)->whereNull('used_at')
                    ->update(['used_at' => now(), 'updated_at' => now()]);
            }

            return [$user, (string) $row->family, $started->timestamp];
        });
    }

    /** Revokes the session the presented cookie belongs to (logout without an access token). */
    public function revokeByToken(string $plain): void
    {
        if ($plain === '' || strlen($plain) > 128) {
            return;
        }
        $this->revokeFamily(DB::table('refresh_tokens')->where('token_hash', hash('sha256', $plain))->value('family'));
    }

    public function revokeFamily(?string $family): void
    {
        if ($family) {
            DB::table('refresh_tokens')->where('family', $family)->whereNull('revoked_at')
                ->update(['revoked_at' => now(), 'updated_at' => now()]);
        }
    }

    public function forget(): Cookie
    {
        return $this->cookie('', now()->subYear());
    }

    private function expiry(Carbon $started): Carbon
    {
        $byRefresh = now()->addMinutes((int) config('jwt.refresh_ttl', 20160));
        $bySession = $started->copy()->addMinutes((int) config('security.session_max_minutes', 43200));

        return $byRefresh->lt($bySession) ? $byRefresh : $bySession;
    }

    private function cookie(string $value, Carbon $expires): Cookie
    {
        return Cookie::create(self::COOKIE, $value, $expires, self::PATH, null,
            (bool) config('security.refresh_cookie_secure', true), true, false, Cookie::SAMESITE_STRICT);
    }
}
