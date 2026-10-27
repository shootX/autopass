<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An app package for one body type. A washes package sells count_washes washes for 1-12 months; a
 * discount package sells 30 days at `percent` off `full_price`, unlimited when count_washes is 0, and
 * the driver pays the rest on site. Its one price row (month 1) is the price of the 30 days.
 */
class Package extends Model
{
    public const TYPE_WASHES = 'washes';

    public const TYPE_DISCOUNT = 'discount';

    public const DISCOUNT_DAYS = 30;

    protected $fillable = [
        'car_type',
        'count_washes',
        'type',
        'full_price',
        'percent',
    ];

    protected $casts = [
        'count_washes' => 'integer',
        'full_price' => 'decimal:2',
        'percent' => 'integer',
    ];

    public function prices()
    {
        return $this->hasMany(PackagePrice::class)->orderBy('month');
    }

    public function priceForMonth(int $month): ?PackagePrice
    {
        return $this->prices->firstWhere('month', $month);
    }

    public function isDiscount(): bool
    {
        return $this->type === self::TYPE_DISCOUNT;
    }

    /** What the driver pays on site at this package's discount, rounded to the tetri. */
    public function toPay(): string
    {
        return CorporatePackagePrice::price((string) $this->full_price, (int) $this->percent);
    }

    /**
     * What a package bought now at this price gets: its end, washes and, for a discount, the price and
     * percent it keeps until it ends.
     *
     * @return array{end_date: Carbon, number_of_washes: int, unlimited: bool, full_price: ?string, discount_percent: ?int}
     */
    public function termsFor(PackagePrice $price): array
    {
        if (! $this->isDiscount()) {
            return ['end_date' => now()->addMonths((int) $price->month), 'number_of_washes' => (int) $this->count_washes,
                'unlimited' => false, 'full_price' => null, 'discount_percent' => null];
        }

        return ['end_date' => now()->addDays(self::DISCOUNT_DAYS), 'number_of_washes' => (int) $this->count_washes,
            'unlimited' => (int) $this->count_washes === 0, 'full_price' => (string) $this->full_price,
            'discount_percent' => (int) $this->percent];
    }
}
