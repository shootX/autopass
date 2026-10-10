<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FleetCar extends Model
{
    protected $fillable = [
        'corporate_client_id',
        'plate',
        'brand',
        'model',
        'source',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(CorporateClient::class, 'corporate_client_id');
    }
}
