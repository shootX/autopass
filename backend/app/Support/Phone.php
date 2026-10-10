<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

class Phone
{
    public const RULE = '/^5\d{2} \d{2} \d{2} \d{2}$/';

    public static function digits(?string $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        if (str_starts_with($digits, '995') && strlen($digits) >= 12) {
            $digits = substr($digits, 3);
        }

        return $digits;
    }

    public static function normalize(?string $value): ?string
    {
        $digits = self::digits($value);

        if (! preg_match('/^5\d{8}$/', $digits)) {
            return null;
        }

        return substr($digits, 0, 3).' '.substr($digits, 3, 2).' '.substr($digits, 5, 2).' '.substr($digits, 7, 2);
    }

    public static function format(?string $value): string
    {
        $normalized = self::normalize($value);

        return $normalized !== null ? '+995 '.$normalized : trim((string) $value);
    }

    public static function publicAddress(?string $address): string
    {
        $address = trim((string) $address);
        if ($address === '') {
            return '';
        }

        $parts = preg_split('/\s*[·|]\s*/u', $address) ?: [];
        $kept = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $digits = preg_replace('/\D+/', '', $part) ?? '';
            $phoneOnly = (bool) preg_match('/^\+?\d[\d\s\-]+$/u', $part) && strlen($digits) >= 9 && strlen($digits) <= 12;
            if ($phoneOnly || str_contains($part, 'ობიექტის მენეჯერი')) {
                $part = trim((string) preg_replace('/\+?\d[\d\s\-]{6,}\d\s*(ობიექტის მენეჯერი)?/u', '', $part));
            }
            if ($part !== '') {
                $kept[] = $part;
            }
        }

        return implode(', ', $kept);
    }

    public static function forSms(?string $value): string
    {
        return '995'.self::digits($value);
    }

    public static function prepare(Request $request, string $key = 'phone'): void
    {
        if (! $request->exists($key)) {
            return;
        }

        $value = $request->input($key);
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $normalized = self::normalize($value);
        if ($normalized !== null) {
            $request->merge([$key => $normalized]);
        }
    }

    public static function matchStored(Request $request, string $key = 'phone'): void
    {
        self::prepare($request, $key);

        $value = $request->input($key);
        if (! is_string($value) || $value === '') {
            return;
        }

        if (User::query()->where('phone', $value)->exists()) {
            return;
        }

        $digits = self::digits($value);
        if ($digits === '') {
            return;
        }

        $stored = User::query()
            ->whereRaw("regexp_replace(phone, '\\D', '', 'g') = ?", [$digits])
            ->value('phone');

        if (is_string($stored) && $stored !== '') {
            $request->merge([$key => $stored]);
        }
    }
}
