<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TbcPayment extends Model
{
    /** A checkout page works this long (Flitt `lifetime`); a pending payment older than this can't be paid any more. */
    public const CHECKOUT_MINUTES = 30;

    protected $guarded = [];

    protected $casts = [
        'amount' => 'float',
        'payload' => 'array',
        'paid_at' => 'datetime',
        'referral_paid' => 'boolean',
        'test_mode' => 'boolean',
        'access_expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
