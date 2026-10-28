<?php

namespace App\Services\Points;

use App\Models\PromoCode;
use App\Support\AppSettings;

/** What a number of points costs: the admin's price per point, its discount, then a promo code's percent. */
class PointsPricing
{
    public static function unitPrice(): float
    {
        return max(0.01, round((float) AppSettings::get('point_price'), 2));
    }

    public static function discountPercent(): int
    {
        return max(0, min(99, (int) AppSettings::get('point_discount')));
    }

    public static function limits(): array
    {
        $min = max(1, (int) AppSettings::get('points_min'));

        return [$min, max($min, (int) AppSettings::get('points_max'))];
    }

    /** The price card the app shows before any amount is typed. */
    public static function card(): array
    {
        [$min, $max] = self::limits();
        $unit = self::unitPrice();
        $percent = self::discountPercent();

        return [
            'point_price' => $unit,
            'discount_percent' => $percent,
            'point_price_final' => round($unit * (100 - $percent) / 100, 4),
            'min' => $min,
            'max' => $max,
            'currency' => 'GEL',
        ];
    }

    public static function quote(int $points, ?PromoCode $promo = null): array
    {
        $unit = self::unitPrice();
        $percent = self::discountPercent();
        $promoPercent = $promo && $promo->kind === PromoCode::KIND_DISCOUNT ? max(0, min(99, $promo->value)) : 0;

        $subtotal = round($points * $unit, 2);
        $afterDiscount = round($subtotal * (100 - $percent) / 100, 2);
        $total = max(0.01, round($afterDiscount * (100 - $promoPercent) / 100, 2));

        return [
            'points' => $points,
            'point_price' => $unit,
            'subtotal' => $subtotal,
            'discount_percent' => $percent,
            'discount' => round($subtotal - $afterDiscount, 2),
            'promo_code' => $promoPercent > 0 ? $promo->code : null,
            'promo_percent' => $promoPercent,
            'promo_discount' => round($afterDiscount - $total, 2),
            'total' => $total,
            'currency' => 'GEL',
        ];
    }
}
