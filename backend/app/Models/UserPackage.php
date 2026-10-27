<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    ];

    protected $casts = ['body_legacy' => 'boolean'];

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
}
