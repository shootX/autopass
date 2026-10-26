<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FleetCar extends Model
{
    public const STATUS_INVITED = 'invited';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_BLOCKED = 'blocked';

    /** The car washes from its share of the company's washes. */
    public const MODE_WASHES = 'washes';

    /** The car washes at the company's discount and the driver pays the rest on site. */
    public const MODE_DISCOUNT = 'discount';

    public const MODES = [self::MODE_WASHES, self::MODE_DISCOUNT];

    protected $fillable = [
        'corporate_client_id',
        'plate',
        'brand',
        'model',
        'source',
        'year',
        'car_type',
        'catalog_source',
        'catalog_brand_id',
        'catalog_model_id',
        'generation_id',
    ];
    // status, driver_phone, user_id, user_car_id and the invite timestamps are set by FleetInvites only;
    // mode, corporate_package_id, washes_left and the QR code by CorporateWashes and CorporateQr.

    protected $casts = [
        'invited_at' => 'datetime',
        'activated_at' => 'datetime',
        'blocked_at' => 'datetime',
        'qr_expires_at' => 'datetime',
        'washes_left' => 'integer',
        'year' => 'integer',
    ];

    /** The body type the company gave; a car added before that goes by the driver's app car. */
    public function carType(): ?string
    {
        return $this->car_type ?: $this->userCar?->carType() ?: null;
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(CorporateClient::class, 'corporate_client_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function userCar(): BelongsTo
    {
        return $this->belongsTo(UserCar::class);
    }

    /** The discount package the company picked for this car; used only in discount mode and while active. */
    public function discountPackage(): BelongsTo
    {
        return $this->belongsTo(CorporatePackage::class, 'corporate_package_id');
    }
}
