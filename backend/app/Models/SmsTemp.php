<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsTemp extends Model
{
    protected $fillable = [
        'type',
        'user_id',
        'code',
        'user_voucher_id'
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
