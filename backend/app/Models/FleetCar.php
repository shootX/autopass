<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FleetCar extends Model
{
    public const STATUS_INVITED = 'invited';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_BLOCKED = 'blocked';

    protected $fillable = [
        'corporate_client_id',
        'plate',
        'brand',
        'model',
        'source',
    ];
    // status, driver_phone, user_id, user_car_id and the invite timestamps are set by FleetInvites only.

    protected $casts = [
        'invited_at' => 'datetime',
        'activated_at' => 'datetime',
        'blocked_at' => 'datetime',
    ];

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
}
