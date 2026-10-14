<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CarBrand extends Model
{
    protected $fillable = ['name', 'img', 'source', 'external_brand_id'];

    public function models()
    {
        return $this->hasMany(CarModel::class, 'brand_id');
    }
}
