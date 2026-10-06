<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarModel extends Model
{
    protected $fillable = [
        'name',
        'brand_id',
        'type',
        'img'
    ];
    const IMAGES = [
        'sedan' => '/assets/img/car_types/sedan_image.png',
        'suv' => '/assets/img/car_types/suv_image.png',
        'hatchback' => '/assets/img/car_types/hatchback_image.png',
        'coupe' => '/assets/img/car_types/coupe_image.png',
        'convertible' => '/assets/img/car_types/convertible_image.png',
        'wagon' => '/assets/img/car_types/wagon_image.png',
        //'van' => '/assets/img/car_types/van_image.png',
        'pickup' => '/assets/img/car_types/pickup_image.png',
    ];
    public function brand(): BelongsTo
    {
        return $this->belongsTo(CarBrand::class, 'brand_id');
    }

    public function getImageAttribute()
    {
        //return self::IMAGES[$this->type] ?? '/assets/images/car_types/sedan_image.png';

        $img = $this->img;
        if (is_null($img)) {
            $brand = $this->brand;
            $img = $brand->img;
            if (is_null($img)) {
                $typeImage = BodyType::imageFor($this->type);
                if ($typeImage) {
                    return $typeImage;
                }

                return self::IMAGES[$this->type] ?? '/assets/images/car_types/sedan_image.png';
            }
        }
        return $img;
    }
}
