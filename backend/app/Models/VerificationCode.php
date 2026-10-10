<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationCode extends Model
{
    protected $fillable = [
        'user_id',
        'code',
        'code_hash',
        'attempts',
        'expires_at',
        'consumed_at',
    ];

    protected $hidden = [
        'code',
        'code_hash',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
        'attempts' => 'integer',
    ];

    public function user() : BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
