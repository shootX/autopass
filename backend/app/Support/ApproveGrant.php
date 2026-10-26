<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * A one-time grant a manager gets from a QR scan and spends on the approval. A static QR, or a
 * screenshot of it, is never enough on its own, and every grant approves at most once.
 */
final class ApproveGrant
{
    public static function issue(string $scope, array $grant, int $minutes = 10): string
    {
        $token = bin2hex(random_bytes(20));
        Cache::put(self::key($scope, $token), $grant, now()->addMinutes($minutes));

        return $token;
    }

    /**
     * Reads and deletes the grant. Under concurrency only the request holding the lock reads it;
     * a parallel one gets 'busy', and the grant is gone once the holder is done.
     *
     * @return array<string, mixed>|'busy'|null
     */
    public static function consume(string $scope, string $token): array|string|null
    {
        if ($token === '') {
            return null;
        }
        $key = self::key($scope, $token);
        $lock = Cache::lock($key.':lock', 10);
        if (! $lock->get()) {
            return 'busy';
        }
        try {
            $grant = Cache::get($key);
            Cache::forget($key);
        } finally {
            $lock->release();
        }

        return is_array($grant) ? $grant : null;
    }

    private static function key(string $scope, string $token): string
    {
        return $scope.':'.hash('sha256', $token);
    }
}
