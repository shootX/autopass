<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsTemp extends Model
{
    protected $fillable = [
        'type',
        'user_id',
        'code',
        'public_id',
        'attempts',
        'expires_at',
        'consumed_at',
        'context',
        'user_voucher_id',
    ];

    protected $hidden = [
        'code',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
        'context' => 'array',
        'attempts' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function userVoucher()
    {
        return $this->belongsTo(UserVoucher::class, 'user_voucher_id');
    }
}
