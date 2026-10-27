<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserPackage extends Model
{
    protected $fillable = [
        'user_id',
        'tbc_payment_id',
        'package_id',
        'price_id',
        'user_car_id',
        'start_date',
        'end_date',
        'number_of_washes',
        'used_washes',
        'rectoken',
        'qr_code',
        'unlimited',
        'full_price',
        'discount_percent',
    ];

    protected $casts = [
        'body_legacy' => 'boolean',
        'unlimited' => 'boolean',
        'full_price' => 'decimal:2',
        'discount_percent' => 'integer',
    ];

    /** A discount package: the driver pays toPay() on site at every wash. */
    public function isDiscount(): bool
    {
        return $this->discount_percent !== null;
    }

    /** What the driver pays on site, at the price and percent the package was bought with. */
    public function toPay(): ?string
    {
        return $this->isDiscount() ? CorporatePackagePrice::price((string) $this->full_price, (int) $this->discount_percent) : null;
    }

    /** An unlimited discount package never runs out of washes, only of days. */
    public function hasWashesLeft(): bool
    {
        return $this->unlimited || (int) $this->used_washes < (int) $this->number_of_washes;
    }

    /** Packages with washes left (unlimited ones always); the end date is checked apart. */
    public function scopeWithWashesLeft(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->where('unlimited', true)->orWhereColumn('used_washes', '<', 'number_of_washes'));
    }

    /** Packages used up: no washes left and not unlimited. */
    public function scopeUsedUp(Builder $query): Builder
    {
        return $query->where('unlimited', false)->whereColumn('used_washes', '>=', 'number_of_washes');
    }

    /**
     * A wash needs the car's body type confirmed. A package bought before body types were confirmed
     * (body_legacy) keeps working until it ends, so nothing already paid stops at the wash.
     */
    public function bodyAllowsWash(): bool
    {
        return (bool) $this->body_legacy || (bool) $this->car?->bodyConfirmed();
    }

    protected $hidden = [
        'rectoken',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package_id');
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(UserCar::class, 'user_car_id');
    }

    public function washes(): HasMany
    {
        return $this->hasMany(PackageWash::class, 'user_package_id');
    }
}
