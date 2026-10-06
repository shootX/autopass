<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTicket extends Model
{
    //
    protected $guarded = [];

    public function ticket() : BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function user() : BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
