<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A company's washes of one body type, the ones given to its cars of that type included. Written by CorporateWashes only. */
class CorporateWashPool extends Model
{
    protected $fillable = ['corporate_client_id', 'car_type'];

    protected $casts = ['balance' => 'integer'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(CorporateClient::class, 'corporate_client_id');
    }
}
