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
    ];

    protected $casts = ['walk_in' => 'boolean'];

    public function washing(): BelongsTo
    {
        return $this->belongsTo(CarWash::class, 'car_wash_id');
    }
}
