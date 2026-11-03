<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    // Removed giveaways are only hidden: the tickets people bought stay.
    use SoftDeletes;

    protected $guarded = [];

    protected $hidden = ['pivot'];

    public function users() : HasMany
    {
        return $this->hasMany(UserTicket::class, 'ticket_id');
    }

    /** Still running today: a giveaway past its date_to is no longer sold or shown in the shop. */
    public function scopeOnSale(Builder $query): Builder
    {
        return $query->whereDate('date_to', '>=', now()->toDateString());
    }

    public function getPhotoAttribute($value)
    {
        return asset($value);
    }
}
