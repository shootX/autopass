<?php

namespace App\Support;

use App\Models\CorporateClient;
use App\Services\CorporateWashes;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** What the washes section of the admin company page and of the company panel shows. */
class CorporateWashPanel
{
    /** $manage is the admin, who also sees inactive packages. Packages are made in the admin's Packages menu. */
    public static function data(CorporateClient $corporate, bool $manage): array
    {
        $packages = $corporate->packages()->with('prices')->orderByDesc('is_active')->orderBy('type')->orderBy('washes');

        return [
            'balance' => CorporateWashes::balance($corporate),
            'packages' => ($manage ? $packages : $packages->where('is_active', true))->get(),
            'moves' => CorporateWashes::ledger($corporate),
            'discounts' => $corporate->discountPackages()->with('prices')->withCount('cars')->get(),
            'history' => self::history($corporate),
            'month' => self::month($corporate),
            'manage' => $manage,
        ];
    }

    /** Every approved wash, newest first, with the price of a discount wash. */
    public static function history(CorporateClient $corporate): LengthAwarePaginator
    {
        return $corporate->washes()
            ->with('washing:id,name')
            ->orderByDesc('id')
            ->paginate(20, ['*'], 'history')
            ->withQueryString();
    }

    /**
     * This month's washes by mode and, for discount washes, the full prices and what drivers paid on site.
     *
     * @return array{washes: int, discount: int, full: float, paid: float}
     */
    public static function month(CorporateClient $corporate): array
    {
        $rows = $corporate->washes()
            ->where('created_at', '>=', now()->startOfMonth())
            ->selectRaw('mode, COUNT(*) AS n, COALESCE(SUM(full_price), 0) AS full_sum, COALESCE(SUM(to_pay), 0) AS paid_sum')
            ->groupBy('mode')
            ->get()
            ->keyBy('mode');
        $discount = $rows->get('discount');

        return [
            'washes' => (int) $rows->get('washes')?->n,
            'discount' => (int) $discount?->n,
            'full' => (float) $discount?->full_sum,
            'paid' => (float) $discount?->paid_sum,
        ];
    }
}
