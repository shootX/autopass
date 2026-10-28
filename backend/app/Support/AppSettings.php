<?php

namespace App\Support;

use App\Models\SiteContent;
use Illuminate\Support\Facades\Cache;

/**
 * Settings the admin edits in the panel, kept as one site_content row ("app"):
 * the point's price and discount, the referral reward and the invoice requisites.
 */
class AppSettings
{
    private const ROW = 'app';

    private const CACHE = 'app_settings';

    public const DEFAULTS = [
        // Price of one point in GEL, and a percent off that price for every purchase.
        'point_price' => 1.0,
        'point_discount' => 0,
        'points_min' => 1,
        'points_max' => 1000,
        // Percent of each paid package of an invited friend that the inviter gets back as points; 0 = off.
        'referral_percent' => 0,
    ];

    public static function all(): array
    {
        return Cache::rememberForever(self::CACHE, function () {
            $stored = SiteContent::query()->where('page', self::ROW)->first()?->data ?? [];

            return array_merge(self::DEFAULTS, ['invoice' => []], $stored);
        });
    }

    public static function get(string $key): mixed
    {
        return self::all()[$key] ?? null;
    }

    public static function store(array $data): void
    {
        $merged = array_merge(self::all(), $data);
        SiteContent::query()->updateOrCreate(['page' => self::ROW], ['data' => $merged]);
        Cache::forget(self::CACHE);
    }

    /** config('invoices') with the admin's requisites over the .env values. */
    public static function invoices(): array
    {
        $config = (array) config('invoices');
        $stored = (array) (self::all()['invoice'] ?? []);
        foreach (array_keys((array) $config['seller']) as $key) {
            if (filled($stored[$key] ?? null)) {
                $config['seller'][$key] = (string) $stored[$key];
            }
        }
        foreach (['prefix', 'due_days', 'vat_rate'] as $key) {
            if (isset($stored[$key]) && $stored[$key] !== '') {
                $config[$key] = $stored[$key];
            }
        }

        return $config;
    }
}
