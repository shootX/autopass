<?php

namespace App\Services;

use App\Models\CorporatePackagePrice;
use App\Models\FleetCar;

/** A company car in discount mode: which row of the company's active discount package fits it. */
class CorporateDiscounts
{
    /** The body type of the driver's own car; unknown until the driver signs in and the car is linked. */
    public static function carType(FleetCar $car): ?string
    {
        return $car->userCar?->model?->type;
    }

    /** The price for this car's body type, or null when the company has no discount package or no row for it. */
    public static function forCar(FleetCar $car): ?CorporatePackagePrice
    {
        $type = self::carType($car);
        $package = $car->client?->discountPackage;
        if ($type === null || ! $package) {
            return null;
        }

        return $package->prices()->where('car_type', $type)->first();
    }

    /** @return array{car_type: string, full_price: float, percent: int, to_pay: float}|null */
    public static function payload(?CorporatePackagePrice $discount): ?array
    {
        if (! $discount) {
            return null;
        }

        return [
            'car_type' => $discount->car_type,
            'full_price' => (float) $discount->full_price,
            'percent' => (int) $discount->percent,
            'to_pay' => (float) $discount->toPay(),
        ];
    }
}
