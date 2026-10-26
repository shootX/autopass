<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A package made by the admin for one company only. A wash package's washes are credited once the
 * invoice is paid; a discount package gives a price per body type, and the driver pays the rest on site.
 */
class CorporatePackage extends Model
{
    public const TYPE_WASHES = 'washes';

    public const TYPE_DISCOUNT = 'discount';

    public const TYPES = [self::TYPE_WASHES, self::TYPE_DISCOUNT];

    protected $fillable = ['title', 'washes', 'price', 'is_active', 'monthly_limit'];
    // corporate_client_id and type are set once, when the package is created; car_type (the body type a
    // wash package is for) by CorporatePackagesController.

    protected $casts = [
        'washes' => 'integer',
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        // Discount packages only: discount washes per car in a calendar month; null is unlimited.
        'monthly_limit' => 'integer',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(CorporateClient::class, 'corporate_client_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(CorporatePackagePrice::class)->orderBy('car_type');
    }

    /** The company cars washing at this discount package. */
    public function cars(): HasMany
    {
        return $this->hasMany(FleetCar::class)->where('mode', FleetCar::MODE_DISCOUNT);
    }

    public function isDiscount(): bool
    {
        return $this->type === self::TYPE_DISCOUNT;
    }
}
