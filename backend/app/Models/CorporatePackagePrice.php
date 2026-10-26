<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One body type of a company's discount package: the full price and the percent off. */
class CorporatePackagePrice extends Model
{
    protected $fillable = [
        'car_type',
        'full_price',
        'percent',
    ];

    protected $casts = [
        'full_price' => 'decimal:2',
        'percent' => 'integer',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(CorporatePackage::class, 'corporate_package_id');
    }

    /** What the driver pays on site, rounded to the tetri. */
    public function toPay(): string
    {
        return self::price((string) $this->full_price, (int) $this->percent);
    }

    public static function price(string $fullPrice, int $percent): string
    {
        $cents = (int) round((float) $fullPrice * 100);

        return number_format(intdiv($cents * (100 - $percent) + 50, 100) / 100, 2, '.', '');
    }
}
