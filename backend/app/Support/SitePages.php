<?php

namespace App\Support;

use App\Models\CarWash;
use App\Models\SiteContent;
use Illuminate\Support\Facades\Cache;

/**
 * The landing's content: defaults from resources/site/{page}.json, the admin's changes from site_contents.
 * Only the admin site editor writes; the landing reads payload() through GET /api/site/{page}.
 */
class SitePages
{
    public const PAGES = ['home', 'business'];

    public const LANGS = ['ka', 'en', 'ru'];

    public const SETTINGS = [
        'price_4' => '60', 'price_8' => '110', 'price_12' => '150', 'hours' => '08:00–22:00',
        'phone' => '', 'email' => '', 'ios_url' => '', 'privacy_url' => '', 'terms_url' => '',
        'branches_from_db' => '1',
    ];

    public static function defaults(string $page): array
    {
        static $cache = [];

        return $cache[$page] ??= json_decode((string) file_get_contents(resource_path("site/{$page}.json")), true);
    }

    public static function stored(string $page): array
    {
        return SiteContent::query()->where('page', $page)->first()?->data ?? [];
    }

    public static function store(string $page, array $data): void
    {
        SiteContent::query()->updateOrCreate(['page' => $page], ['data' => $data]);
        self::forget();
    }

    public static function forget(): void
    {
        foreach (self::PAGES as $page) {
            Cache::forget("site_payload_{$page}");
        }
    }

    /** Text of every key in the three languages, the admin's value over the default. */
    public static function texts(string $page): array
    {
        $stored = self::stored($page)['texts'] ?? [];
        $out = [];
        foreach (self::defaults($page)['texts'] as $key => $default) {
            foreach (self::LANGS as $lang) {
                $out[$key][$lang] = $stored[$key][$lang] ?? $default[$lang];
            }
            $out[$key]['group'] = $default['group'];
        }

        return $out;
    }

    /** Each list as items of [lang => [field => value]]. */
    public static function lists(string $page): array
    {
        $stored = self::stored($page)['lists'] ?? [];
        $out = [];
        foreach (self::defaults($page)['lists'] as $name => $list) {
            $items = $stored[$name] ?? array_map(fn (array $item) => self::named($item, $list['fields']), $list['items']);
            $out[$name] = ['group' => $list['group'], 'fields' => $list['fields'], 'items' => $items];
        }

        return $out;
    }

    /** Sections in the admin's order, with their visibility. */
    public static function sections(string $page): array
    {
        $stored = self::stored($page)['sections'] ?? [];
        $out = [];
        foreach (self::defaults($page)['sections'] as $i => $id) {
            $out[$id] = [
                'id' => $id,
                'visible' => (bool) ($stored[$id]['visible'] ?? true),
                'order' => (int) ($stored[$id]['order'] ?? $i + 1),
            ];
        }
        uasort($out, fn (array $a, array $b) => $a['order'] <=> $b['order']);

        return $out;
    }

    public static function settings(): array
    {
        return array_merge(self::SETTINGS, array_intersect_key(self::stored('settings'), self::SETTINGS));
    }

    public static function payload(string $page): array
    {
        return Cache::rememberForever("site_payload_{$page}", fn () => self::build($page));
    }

    private static function build(string $page): array
    {
        $texts = self::texts($page);
        $lists = self::lists($page);
        $text = [];
        foreach (self::LANGS as $lang) {
            foreach ($texts as $key => $value) {
                if (str_starts_with($key, 'plan.')) {
                    $text[$lang]['plan'][substr($key, 5)] = $value[$lang];
                } else {
                    $text[$lang][$key] = $value[$lang];
                }
            }
            foreach ($lists as $name => $list) {
                $rows = array_map(fn (array $item) => array_map(fn ($f) => (string) ($item[$lang][$f] ?? ''), $list['fields']), $list['items']);
                match ($name) {
                    'faq' => $text[$lang]['faq'] = array_map(fn (array $r) => ['', $r[0], $r[1]], $rows),
                    'plan.l', 'plan.lu' => $text[$lang]['plan'][substr($name, 5)] = array_column($rows, 0),
                    default => $text[$lang][$name] = $rows,
                };
            }
        }

        $s = self::settings();

        return [
            'success' => true,
            'text' => $text,
            'sections' => array_values(array_map(fn (array $x) => ['id' => $x['id'], 'visible' => $x['visible']], self::sections($page))),
            'config' => [
                'prices' => ['4' => 0 + $s['price_4'], '8' => 0 + $s['price_8'], '12' => 0 + $s['price_12']],
                'hours' => $s['hours'],
                'phone' => $s['phone'],
                'email' => $s['email'],
                'ios_url' => $s['ios_url'],
                'privacy_url' => $s['privacy_url'],
                'terms_url' => $s['terms_url'],
            ],
            'branches' => $page === 'home' && $s['branches_from_db'] === '1' ? self::branches() : null,
        ];
    }

    /** Branches from the car washes the admin already keeps; null when there are none, so the list is used. */
    private static function branches(): ?array
    {
        $rows = CarWash::query()->orderBy('id')->get(['name', 'address', 'work_time_start', 'work_time_end'])
            ->map(fn (CarWash $w) => [
                (string) $w->name,
                (string) $w->address,
                $w->work_time_start && $w->work_time_end
                    ? substr((string) $w->work_time_start, 0, 5).'–'.substr((string) $w->work_time_end, 0, 5)
                    : '',
            ])->all();

        return $rows ?: null;
    }

    private static function named(array $item, array $fields): array
    {
        $out = [];
        foreach (self::LANGS as $lang) {
            foreach ($fields as $i => $field) {
                $out[$lang][$field] = (string) ($item[$lang][$i] ?? '');
            }
        }

        return $out;
    }
}
