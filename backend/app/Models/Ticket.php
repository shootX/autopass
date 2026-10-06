<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    protected $guarded = [];

    protected $hidden = ['pivot'];

    public function users() : HasMany
    {
        return $this->hasMany(UserTicket::class, 'ticket_id');
    }

    public function getPhotoAttribute($value)
    {
        return asset($value);
    }
}
