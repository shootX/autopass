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
        'note',
    ];
    // username, password, credentials_custom, api_token, password_must_change, temp_password_expires_at,
    // session_version, credentials_retired_at and ban are set explicitly by server code only.

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'vat_payer' => 'boolean',
        'credentials_custom' => 'boolean',
        'password' => 'hashed',
        'password_must_change' => 'boolean',
        'temp_password_expires_at' => 'datetime',
        'credentials_retired_at' => 'datetime',
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
