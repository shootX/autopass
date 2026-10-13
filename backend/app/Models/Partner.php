<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Partner extends Authenticatable implements JWTSubject
{

    protected $guarded = [];

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
