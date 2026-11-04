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

    /** Phones one account may get pushes on; a new one past this replaces the one not seen the longest. */
    public const MAX_PER_USER = 10;

    /** Drops one phone's token from one account (sign-out on that phone). Nothing when no token is given. */
    public static function forgetDevice(int $userId, mixed $token): void
    {
        if ($userId <= 0 || ! is_string($token) || ! preg_match('/^[0-9a-fA-F]{32,200}$/', $token)) {
            return;
        }
        static::query()->where('user_id', $userId)->where('push_id', strtolower($token))->delete();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
