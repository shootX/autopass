<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CorporateClient extends Model
{
    protected $fillable = [
        'name',
        'legal_form',
        'identification_code',
        'legal_address',
        'actual_address',
        'bank_name',
        'bank_code',
        'bank_account',
        'contact',
        'phone',
        'email',
        'website',
        'vat_payer',
        'username',
        'password',
        'credentials_custom',
        'note',
        'api_token',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'vat_payer' => 'boolean',
        'credentials_custom' => 'boolean',
    ];

    public function cars(): HasMany
    {
        return $this->hasMany(FleetCar::class);
    }

    public static function makeToken(): string
    {
        return Str::random(40);
    }
}
