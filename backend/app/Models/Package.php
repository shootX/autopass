<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    protected $fillable = [
        'car_type',
        'count_washes',
    ];
    public function prices()
    {
        return $this->hasMany(PackagePrice::class)->orderBy('month');
    }

    public function priceForMonth(int $month): ?PackagePrice
    {
        return $this->prices->firstWhere('month', $month);
    }
}
