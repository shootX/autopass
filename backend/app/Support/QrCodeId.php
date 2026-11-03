<?php

namespace App\Support;

/**
 * A short public name for a QR code, sent with the code (X-QR-Id) so the app can ask whether the code it shows is
 * still the live one: another phone of the same driver may have taken a newer code since. It names a code, it
 * can't be turned back into one.
 */
class QrCodeId
{
    public static function of(string $code): string
    {
        return substr(hash('sha256', $code), 0, 16);
    }

    /** True when no id was sent (an app before X-QR-Id) or it names this code. */
    public static function shown(?string $code, ?string $id): bool
    {
        $id = (string) $id;

        return $id === '' || ($code !== null && $code !== '' && hash_equals(self::of($code), $id));
    }
}
