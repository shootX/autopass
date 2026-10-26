<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One wash of a company car, approved by a manager after the QR scan. */
class CorporateWash extends Model
{
    protected $fillable = [
        'corporate_client_id',
        'fleet_car_id',
        'plate',
        'mode',
        'user_id',
        'car_wash_id',
        'manager_id',
        'appointment_id',
        'walk_in',
        'car_type',
        'corporate_package_id',
        'full_price',
        'discount_percent',
        'to_pay',
    ];

    protected $casts = [
        'walk_in' => 'boolean',
        'full_price' => 'decimal:2',
        'discount_percent' => 'integer',
        'to_pay' => 'decimal:2',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(CorporatePackage::class, 'corporate_package_id');
    }

    public function washing(): BelongsTo
    {
        return $this->belongsTo(CarWash::class, 'car_wash_id');
    }
}
