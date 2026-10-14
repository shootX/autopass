<?php

namespace App\Support;

use App\Models\User;

/**
 * Who may start a test (sandbox) payment.
 *
 * Outside production: admins and phones in TEST_PAYMENT_PHONES.
 * In production: nobody, unless TEST_PAYMENT_ALLOW_PRODUCTION=true, and then still only those people.
 */
class TestPayments
{
    public static function available(): bool
    {
        if (! app()->environment('production')) {
            return true;
        }

        return config('security.test_payment_allow_production') === true;
    }

    public static function allowed(?User $user): bool
    {
        if (! $user || ! self::available() || $user->ban) {
            return false;
        }

        if ((int) $user->role === User::ROLE_ADMIN) {
            return true;
        }

        $phone = Phone::normalize($user->phone);
        if ($phone === null) {
            return false;
        }

        foreach (config('security.test_payment_phones', []) as $allowed) {
            if (Phone::normalize((string) $allowed) === $phone) {
                return true;
            }
        }

        return false;
    }
}
