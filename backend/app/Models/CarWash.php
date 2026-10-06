<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CarWash extends Model
{
    protected $fillable = [
        'name',
        'address',
        'work_time_start',
        'work_time_end',
        'location',
        'manager_id'
    ];
    public function manager() : HasOne
    {
        return $this->hasOne(User::class, 'id', 'manager_id');
    }

    public function services() : BelongsToMany
    {
        return $this->belongsToMany(CarWashService::class, 'car_wash_services_lists', 'car_wash_id', 'car_wash_service_id');
    }

    public function reviews() : HasMany
    {
        return $this->hasMany(Review::class, 'car_wash_id', 'id');
    }

    public function appointments() : HasMany
    {
        return $this->hasMany(Appointment::class, 'car_wash_id');
    }
}
