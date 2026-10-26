<?php

namespace App\Services;

use App\Models\Appointment;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;

class WashReportQuery
{
    public static function make(array $filters): Builder
    {
        $query = Appointment::query()
            ->with([
                'user',
                'car.model.brand',
                'car.package.package',
                'washing.manager',
                'services',
            ]);

        if ($id = self::int($filters, 'id')) {
            $query->where('id', $id);
        }

        if ($from = self::text($filters, 'date_from')) {
            $query->whereDate('date', '>=', $from);
        }
        if ($to = self::text($filters, 'date_to')) {
            $query->whereDate('date', '<=', $to);
        }
        if ($from = self::text($filters, 'time_from')) {
            $query->where('time', '>=', $from);
        }
        if ($to = self::text($filters, 'time_to')) {
            $query->where('time', '<=', $to);
        }
        if ($from = self::text($filters, 'created_from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = self::text($filters, 'created_to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        if (self::text($filters, 'approved') !== null && in_array((string) $filters['approved'], ['0', '1', '2', '3', '4', '5'], true)) {
            $query->where('approved', (int) $filters['approved']);
        }

        if ($washId = self::int($filters, 'car_wash_id')) {
            $query->where('car_wash_id', $washId);
        }

        if ($qr = self::text($filters, 'qr_code')) {
            $query->where('qr_code', 'ilike', self::like($qr));
        }

        if ($client = self::text($filters, 'client')) {
            $like = self::like($client);
            $query->whereHas('user', function (Builder $user) use ($like) {
                $user->where(function (Builder $where) use ($like) {
                    $where->where('name', 'ilike', $like)
                        ->orWhere('surname', 'ilike', $like)
                        ->orWhereRaw("concat_ws(' ', surname, name) ilike ?", [$like])
                        ->orWhereRaw("concat_ws(' ', name, surname) ilike ?", [$like]);
                });
            });
        }

        if ($phone = self::text($filters, 'phone')) {
            $formatted = Phone::normalize($phone);
            $query->whereHas('user', function (Builder $user) use ($phone, $formatted) {
                if ($formatted) {
                    $user->where('phone', $formatted);

                    return;
                }
                $user->where('phone', 'ilike', self::like($phone));
            });
        }

        if ($email = self::text($filters, 'email')) {
            $like = self::like($email);
            $query->whereHas('user', fn (Builder $user) => $user->where('email', 'ilike', $like));
        }

        if ($managerId = self::int($filters, 'manager_id')) {
            $query->whereHas('washing', fn (Builder $wash) => $wash->where('manager_id', $managerId));
        }

        if ($address = self::text($filters, 'address')) {
            $like = self::like($address);
            $query->whereHas('washing', fn (Builder $wash) => $wash->where('address', 'ilike', $like));
        }

        if ($serviceId = self::int($filters, 'service_id')) {
            $query->whereHas('services', fn (Builder $service) => $service->where('car_wash_services.id', $serviceId));
        }

        if (self::text($filters, 'plate') || self::int($filters, 'brand_id') || self::text($filters, 'car_type')) {
            $query->whereHas('car', function (Builder $car) use ($filters) {
                if ($plate = self::text($filters, 'plate')) {
                    $car->where('plate', 'ilike', self::like($plate));
                }
                if ($brandId = self::int($filters, 'brand_id')) {
                    $car->whereHas('model', fn (Builder $model) => $model->where('brand_id', $brandId));
                }
                // The car's own body type, else its model's (UserCar::carType).
                if ($type = self::text($filters, 'car_type')) {
                    $car->where(fn (Builder $own) => $own->where('car_type', $type)
                        ->orWhere(fn (Builder $byModel) => $byModel->whereNull('car_type')
                            ->whereHas('model', fn (Builder $model) => $model->where('type', $type))));
                }
            });
        }

        if (self::text($filters, 'package_type') || self::int($filters, 'count_washes')) {
            $query->whereHas('car.package.package', function (Builder $package) use ($filters) {
                if ($type = self::text($filters, 'package_type')) {
                    $package->where('car_type', $type);
                }
                if ($count = self::int($filters, 'count_washes')) {
                    $package->where('count_washes', $count);
                }
            });
        }

        return $query->orderByDesc('date')->orderByDesc('time')->orderByDesc('id');
    }

    /**
     * Washes a company may see: bookings of its cars' linked app cars, made while the car was
     * active for that company (from activation, until a block). A plate typed into the fleet
     * alone shows nothing.
     */
    public static function forFleet(array $filters, int $corporateClientId, ?string $plate = null): Builder
    {
        $query = self::make(array_diff_key($filters, ['plate' => true]));
        $plate = $plate === null ? null : FleetCars::plate($plate);

        return $query->whereExists(function ($fleet) use ($corporateClientId, $plate) {
            $fleet->selectRaw('1')
                ->from('fleet_cars')
                ->whereColumn('fleet_cars.user_car_id', 'appointments.car_id')
                ->where('fleet_cars.corporate_client_id', $corporateClientId)
                ->whereNotNull('fleet_cars.activated_at')
                ->whereColumn('appointments.created_at', '>=', 'fleet_cars.activated_at')
                ->where(function ($open) {
                    $open->whereNull('fleet_cars.blocked_at')
                        ->orWhereColumn('appointments.created_at', '<', 'fleet_cars.blocked_at');
                });
            if ($plate !== null && $plate !== '') {
                $fleet->where('fleet_cars.plate', $plate);
            }
        });
    }

    public static function statusLabel(mixed $approved): string
    {
        if ($approved === true || $approved === 1 || $approved === '1' || $approved === 't') {
            return __('admin.confirmed');
        }

        if ($approved === false || $approved === 0 || $approved === '0' || $approved === 'f' || $approved === null) {
            return __('admin.pending');
        }

        return match ((string) $approved) {
            '2' => __('admin.cancelled'),
            '3' => __('admin.completed'),
            '4' => __('admin.expired'),
            '5' => __('admin.rescheduled'),
            default => (string) $approved,
        };
    }

    private static function text(array $filters, string $key): ?string
    {
        if (! array_key_exists($key, $filters) || $filters[$key] === null) {
            return null;
        }

        $value = trim((string) $filters[$key]);

        return $value === '' ? null : $value;
    }

    private static function int(array $filters, string $key): ?int
    {
        $value = self::text($filters, $key);
        if ($value === null || ! ctype_digit($value)) {
            return null;
        }

        $number = (int) $value;

        return $number > 0 ? $number : null;
    }

    private static function like(string $value): string
    {
        return '%'.addcslashes($value, '%_\\').'%';
    }
}
