<?php

namespace App\Services\Push;

use App\Support\AppSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends a notification straight to Apple (APNs, token auth with a .p8 key). The key, Key ID, Team ID
 * and bundle id come from Admin > Push, falling back to APNS_* in .env.
 */
class ApnsClient
{
    public const SENT = 'sent';

    /** Apple says the token is dead (app removed, token rotated): the caller forgets it. */
    public const GONE = 'gone';

    public const FAILED = 'failed';

    private const JWT_CACHE = 'apns_jwt';

    /** @return array{key_id: string, team_id: string, bundle_id: string, key: string} */
    public static function settings(): array
    {
        $stored = (array) (AppSettings::get('apns') ?? []);
        $config = (array) config('services.apns');
        $key = '';
        if (filled($stored['key'] ?? null)) {
            try {
                $key = Crypt::decryptString((string) $stored['key']);
            } catch (\Throwable) {
                $key = '';
            }
        }

        return [
            'key_id' => (string) (($stored['key_id'] ?? null) ?: ($config['key_id'] ?? '')),
            'team_id' => (string) (($stored['team_id'] ?? null) ?: ($config['team_id'] ?? '')),
            'bundle_id' => (string) (($stored['bundle_id'] ?? null) ?: ($config['bundle_id'] ?? '')),
            'key' => $key !== '' ? $key : (string) ($config['key'] ?? ''),
        ];
    }

    public static function configured(): bool
    {
        $s = self::settings();

        return $s['key_id'] !== '' && $s['team_id'] !== '' && $s['bundle_id'] !== '' && $s['key'] !== '';
    }

    /** Saves the admin's values; an empty key field keeps the key already stored. */
    public static function store(array $data): void
    {
        $current = (array) (AppSettings::get('apns') ?? []);
        $next = [
            'key_id' => trim((string) ($data['key_id'] ?? '')),
            'team_id' => trim((string) ($data['team_id'] ?? '')),
            'bundle_id' => trim((string) ($data['bundle_id'] ?? '')),
            'key' => $current['key'] ?? null,
        ];
        if (filled($data['key'] ?? null)) {
            $next['key'] = Crypt::encryptString(trim((string) $data['key']));
        }
        AppSettings::store(['apns' => $next]);
        Cache::forget(self::JWT_CACHE);
    }

    public function send(string $token, string $title, string $body, array $data = [], bool $sandbox = false): string
    {
        $s = self::settings();
        if (! self::configured()) {
            Log::warning('APNs is not configured; push skipped');

            return self::FAILED;
        }
        $jwt = $this->jwt($s);
        if ($jwt === null) {
            return self::FAILED;
        }

        $host = $sandbox ? 'https://api.sandbox.push.apple.com' : 'https://api.push.apple.com';
        $payload = ['aps' => ['alert' => ['title' => $title, 'body' => $body], 'sound' => 'default']] + $data;

        try {
            $response = Http::withOptions(['version' => 2.0])->timeout(10)->withHeaders([
                'authorization' => 'bearer '.$jwt,
                'apns-topic' => $s['bundle_id'],
                'apns-push-type' => 'alert',
                'apns-priority' => '10',
            ])->post($host.'/3/device/'.$token, $payload);
        } catch (\Throwable $e) {
            Log::warning('APNs request failed', ['error' => $e->getMessage()]);

            return self::FAILED;
        }

        if ($response->successful()) {
            return self::SENT;
        }
        $reason = (string) $response->json('reason');
        if ($response->status() === 410 || in_array($reason, ['BadDeviceToken', 'Unregistered', 'DeviceTokenNotForTopic'], true)) {
            return self::GONE;
        }
        if ($reason === 'ExpiredProviderToken' || $reason === 'InvalidProviderToken') {
            Cache::forget(self::JWT_CACHE);
        }
        Log::warning('APNs refused a push', ['status' => $response->status(), 'reason' => $reason]);

        return self::FAILED;
    }

    /** Apple accepts a provider token for an hour; it is reused for 50 minutes. */
    private function jwt(array $s): ?string
    {
        return Cache::remember(self::JWT_CACHE, now()->addMinutes(50), function () use ($s) {
            $key = openssl_pkey_get_private($s['key']);
            if ($key === false) {
                Log::warning('APNs key is not a valid .p8 private key');

                return null;
            }
            $head = self::base64url(json_encode(['alg' => 'ES256', 'kid' => $s['key_id']]));
            $claims = self::base64url(json_encode(['iss' => $s['team_id'], 'iat' => time()]));
            if (! openssl_sign($head.'.'.$claims, $der, $key, OPENSSL_ALGO_SHA256)) {
                return null;
            }

            return $head.'.'.$claims.'.'.self::base64url(self::derToRaw($der));
        }) ?: null;
    }

    private static function base64url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /** openssl gives an ASN.1 DER signature; a JWT carries r and s as 32 bytes each. */
    private static function derToRaw(string $der): string
    {
        $offset = 3 + (ord($der[1]) & 0x80 ? 1 : 0);
        $rLength = ord($der[$offset]);
        $r = substr($der, $offset + 1, $rLength);
        $offset += 1 + $rLength + 1;
        $sLength = ord($der[$offset]);
        $s = substr($der, $offset + 1, $sLength);

        return str_pad(ltrim($r, "\0"), 32, "\0", STR_PAD_LEFT).str_pad(ltrim($s, "\0"), 32, "\0", STR_PAD_LEFT);
    }
}
