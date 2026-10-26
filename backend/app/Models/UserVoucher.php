<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserVoucher extends Model
{
    //
    protected $guarded = [];

    public function user() : BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function voucher() : BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'voucher_id');
    }

    public function partner() : BelongsTo
    {
        return $this->belongsTo(Partner::class, 'used_by_partner_id');
    }
}
