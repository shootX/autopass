<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A wash package made for one company only; the admin credits its washes once the invoice is paid. */
class CorporatePackage extends Model
{
    protected $fillable = ['corporate_client_id', 'title', 'washes', 'price', 'is_active'];

    protected $casts = [
        'washes' => 'integer',
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(CorporateClient::class, 'corporate_client_id');
    }
}
