<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = [
        'user_id',
        'car_wash_id',
        'stars',
        'text',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function washing()
    {
        return $this->belongsTo(CarWash::class, 'car_wash_id');
    }
}
