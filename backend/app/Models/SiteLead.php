<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteLead extends Model
{
    protected $fillable = ['company', 'phone', 'lang', 'ip', 'handled_at'];

    protected $casts = ['handled_at' => 'datetime'];
}
