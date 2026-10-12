<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackageWash extends Model
{
    protected $fillable = ['user_package_id', 'car_wash_id', 'manager_id', 'appointment_id', 'walk_in'];

    protected $casts = ['walk_in' => 'boolean'];
}
