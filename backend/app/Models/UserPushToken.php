<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPushToken extends Model
{
    /** A raw APNs device token the server sends to itself. Older rows ('onesignal') are never used. */
    public const KIND_APNS = 'apns';

    protected $guarded = [];

    protected $casts = [
        'sandbox' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
