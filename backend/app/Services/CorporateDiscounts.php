<?php

namespace App\Services;

use App\Models\CorporateClient;
use App\Models\CorporatePackage;
use App\Models\CorporatePackagePrice;
use App\Models\CorporateWash;
use App\Models\FleetCar;
use Illuminate\Support\Collection;

/**
 * A company car in discount mode washes at the discount package the company picked for it: the row
 * for the car's body type. A company may have several active discount packages.
 */
class CorporateDiscounts
{
    /** The body type of the driver's own car; unknown until the driver signs in and the car is linked. */
    public static function carType(FleetCar $car): ?string
    {
        return $car->userCar?->carType();
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

    /** The car's discount washes since the 1st of this month, at any package. */
    public static function usedThisMonth(FleetCar $car): int
    {
        return CorporateWash::query()
            ->where('fleet_car_id', $car->id)
            ->where('mode', FleetCar::MODE_DISCOUNT)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
    }

    /** @return Collection<int, int> each company car's discount washes since the 1st, by fleet car id */
    public static function usedThisMonthByCar(CorporateClient $client): Collection
    {
        return CorporateWash::query()
            ->where('corporate_client_id', $client->id)
            ->where('mode', FleetCar::MODE_DISCOUNT)
            ->where('created_at', '>=', now()->startOfMonth())
            ->selectRaw('fleet_car_id, COUNT(*) AS n')
            ->groupBy('fleet_car_id')
            ->pluck('n', 'fleet_car_id')
            ->map(fn ($n) => (int) $n);
    }

    /** Discount washes the car has left this month under its package's cap; null when there is no cap. */
    public static function left(FleetCar $car): ?int
    {
        $limit = self::packageOf($car)?->monthly_limit;

        return $limit === null ? null : max(0, $limit - self::usedThisMonth($car));
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

    /**
     * The price row as the apps read it; with the car, also the monthly cap and what is left of it.
     *
     * @return array{car_type: string, full_price: float, percent: int, to_pay: float, monthly_limit?: ?int, left?: ?int}|null
     */
    public static function payload(?CorporatePackagePrice $discount, ?FleetCar $car = null): ?array
    {
        if (! $discount) {
            return null;
        }

        return [
            'car_type' => $discount->car_type,
            'full_price' => (float) $discount->full_price,
            'percent' => (int) $discount->percent,
            'to_pay' => (float) $discount->toPay(),
        ] + ($car ? ['monthly_limit' => self::packageOf($car)?->monthly_limit, 'left' => self::left($car)] : []);
    }
}
