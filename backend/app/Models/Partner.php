<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Partner extends Authenticatable implements JWTSubject
{
    // Security columns (password, ban, password_must_change, temp_password_expires_at, token_version) are set explicitly.
    protected $fillable = ['name', 'description', 'login', 'email', 'photo'];

    protected $hidden = ['password', 'created_at', 'updated_at'];

    protected $casts = [
        'password' => 'hashed',
        'password_must_change' => 'boolean',
        'temp_password_expires_at' => 'datetime',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'tv' => (int) $this->token_version,
            'must_change_password' => (bool) $this->password_must_change,
        ];
    }
}
