<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A ledger row: every change of a company's wash pool or of one car's washes. Written by CorporateWashes only. */
class CorporateWashMove extends Model
{
    public const TYPE_CREDIT = 'credit';

    public const TYPE_CORRECTION = 'correction';

    public const TYPE_ALLOCATE = 'allocate';

    public const TYPE_WASH = 'wash';

    /** The car was deleted and its unused washes went back to the pool. */
    public const TYPE_RELEASE = 'release';

    protected $fillable = [
        'corporate_client_id',
        'type',
        'amount',
        'balance_after',
        'fleet_car_id',
        'plate',
        'car_balance_after',
        'corporate_package_id',
        'corporate_wash_id',
        'actor',
        'actor_id',
        'note',
    ];

    protected $casts = [
        'amount' => 'integer',
        'balance_after' => 'integer',
        'car_balance_after' => 'integer',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(CorporatePackage::class, 'corporate_package_id');
    }

    public function wash(): BelongsTo
    {
        return $this->belongsTo(CorporateWash::class, 'corporate_wash_id');
    }
}
