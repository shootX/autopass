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
        'api_token_hash',
    ];

    protected $casts = [
        'api_token_used_at' => 'datetime',
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
        return Str::random(48);
    }

    /** Plain key of a just-issued token; lives only in memory, never in the database. */
    private ?string $plainApiToken = null;

    /** Assigning api_token stores only its sha256 and last 4 chars. */
    public function setApiTokenAttribute(?string $plain): void
    {
        $this->plainApiToken = $plain;
        $this->attributes['api_token'] = null;
        $this->attributes['api_token_hash'] = $plain === null ? null : self::hashToken($plain);
        $this->attributes['api_token_last4'] = $plain === null ? null : substr($plain, -4);
    }

    public function getApiTokenAttribute(): ?string
    {
        return $this->plainApiToken;
    }

    public static function hashToken(string $plain): string
    {
        return hash('sha256', $plain);
    }

    public static function findByToken(string $plain): ?self
    {
        return self::query()->where('api_token_hash', self::hashToken($plain))->first();
    }
}
