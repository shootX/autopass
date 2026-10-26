<?php

namespace App\Services;

use App\Models\CorporateClient;
use App\Models\CorporatePackage;
use App\Models\CorporatePackagePrice;
use App\Models\FleetCar;

/**
 * A company car in discount mode washes at the discount package the company picked for it: the row
 * for the car's body type. A company may have several active discount packages.
 */
class CorporateDiscounts
{
    /** The body type of the driver's own car; unknown until the driver signs in and the car is linked. */
    public static function carType(FleetCar $car): ?string
    {
        return $car->userCar?->model?->type;
    }

    /** The car's discount package while it is active, else null (switched off, deleted or never picked). */
    public static function packageOf(FleetCar $car): ?CorporatePackage
    {
        $package = $car->discountPackage;

        return $package?->is_active ? $package : null;
    }

    /** The price for this car's body type, or null when the car has no active package or it has no row for the type. */
    public static function forCar(FleetCar $car): ?CorporatePackagePrice
    {
        $type = self::carType($car);
        $package = self::packageOf($car);
        if ($type === null || ! $package) {
            return null;
        }

        return $package->prices->firstWhere('car_type', $type);
    }

    /** One of the company's active discount packages, the only ones a car can be given. */
    public static function find(CorporateClient $client, int $id): ?CorporatePackage
    {
        return $client->discountPackages()->whereKey($id)->first();
    }

    /** The package a car gets when it is switched to discount without one: its own if still active, or the company's only one. */
    public static function fallback(FleetCar $car): ?CorporatePackage
    {
        if ($own = self::packageOf($car)) {
            return $own;
        }
        $packages = $car->client?->discountPackages()->limit(2)->get();

        return $packages?->count() === 1 ? $packages->first() : null;
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
