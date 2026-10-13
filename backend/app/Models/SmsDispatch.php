<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsDispatch extends Model
{
    protected $fillable = [
        'reference',
        'purpose',
        'phone_mask',
        'status',
        'error_code',
        'body_encrypted',
        'expires_at',
    ];

    protected $hidden = [
        'body_encrypted',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'error_code' => 'integer',
    ];
}
