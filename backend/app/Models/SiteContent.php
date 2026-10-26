<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteContent extends Model
{
    protected $fillable = ['page', 'data'];

    protected $casts = ['data' => 'array'];
}
