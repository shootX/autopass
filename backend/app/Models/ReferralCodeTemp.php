<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralCodeTemp extends Model
{
    protected $fillable = [
        'hash',
        'ref_code'
    ];
}
