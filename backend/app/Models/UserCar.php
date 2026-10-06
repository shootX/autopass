<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserCar extends Model
{
    //
    protected $fillable = [
        'user_id',
        'model_id',
        'plate',
    ];

    public function model() {
        return $this->belongsTo(CarModel::class, 'model_id');
    }

    public function package()
    {
        return $this->hasOne(UserPackage::class, 'user_car_id');
    }

}
