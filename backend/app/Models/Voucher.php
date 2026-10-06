<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Voucher extends Model
{
    //
    protected $hidden = ['category_id', 'created_at', 'updated_at', 'pivot'];
    protected $guarded = [];

    public function category() : BelongsTo
    {
        return $this->belongsTo(VoucherCategory::class);
    }

    public function getPhotoAttribute($value)
    {
        return asset($value);
    }
}
