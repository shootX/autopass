<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Every wash a manager approved by QR, one list: a driver's own package (package_washes) and a company
 * car (corporate_washes). These are the washes that really happened at a branch, booked or walk-in, so
 * branch and manager totals for settling with partners come from here, not from bookings.
 * Joins are plain (no soft-delete scope): a car, package or branch removed later still shows in history.
 */
class QrWashReport
{
    public const KINDS = ['package', 'corporate'];

    public static function make(array $filters): Builder
    {
        $kind = self::text($filters, 'kind');
        $parts = [];
        if ($kind === null || $kind === 'package') {
            $parts[] = self::filtered(self::packageWashes(), $filters);
        }
        if ($kind === null || $kind === 'corporate') {
            $parts[] = self::filtered(self::corporateWashes(), $filters);
        }
        $union = array_shift($parts);
        foreach ($parts as $part) {
            $union->unionAll($part);
        }

        return DB::query()->fromSub($union, 'q')
            ->leftJoin('car_washes as cw', 'cw.id', '=', 'q.car_wash_id')
            ->leftJoin('users as m', 'm.id', '=', 'q.manager_id')
            ->select('q.*', 'cw.name as wash_name', 'cw.address as wash_address', 'm.name as manager_name', 'm.surname as manager_surname')
            ->orderByDesc('q.created_at')
            ->orderByDesc('q.id');
    }

    /** @return array{washes: int, package: int, corporate: int, paid: float} */
    public static function totals(array $filters): array
    {
        $rows = DB::query()->fromSub(self::make($filters)->reorder(), 't')
            ->selectRaw('kind, COUNT(*) AS n, COALESCE(SUM(to_pay), 0) AS paid')
            ->groupBy('kind')
            ->get()
            ->keyBy('kind');

        return [
            'washes' => (int) $rows->sum('n'),
            'package' => (int) ($rows->get('package')->n ?? 0),
            'corporate' => (int) ($rows->get('corporate')->n ?? 0),
            // Discount washes: what drivers paid on site.
            'paid' => round((float) $rows->sum('paid'), 2),
        ];
    }

    public static function client(object $row): string
    {
        $name = trim(($row->client_name ?? '').' '.($row->client_surname ?? ''));

        return $row->kind === 'corporate' ? trim(($row->company ?? '').($name !== '' ? ' · '.$name : '')) : $name;
    }

    public static function manager(object $row): string
    {
        return trim(($row->manager_name ?? '').' '.($row->manager_surname ?? ''));
    }

    private static function packageWashes(): Builder
    {
        return DB::table('package_washes as w')
            ->join('user_packages as up', 'up.id', '=', 'w.user_package_id')
            ->leftJoin('user_cars as c', 'c.id', '=', 'up.user_car_id')
            ->leftJoin('packages as p', 'p.id', '=', 'up.package_id')
            ->leftJoin('users as u', 'u.id', '=', 'up.user_id')
            ->selectRaw("'package' AS kind, w.id, w.created_at, w.car_wash_id, w.manager_id, w.appointment_id, w.walk_in,
                c.plate AS plate, p.car_type AS car_type, u.name AS client_name, u.surname AS client_surname,
                NULL AS company, p.type AS detail, p.count_washes AS detail_count, w.full_price, w.to_pay");
    }

    private static function corporateWashes(): Builder
    {
        return DB::table('corporate_washes as w')
            ->leftJoin('corporate_clients as cc', 'cc.id', '=', 'w.corporate_client_id')
            ->leftJoin('users as u', 'u.id', '=', 'w.user_id')
            ->selectRaw("'corporate' AS kind, w.id, w.created_at, w.car_wash_id, w.manager_id, w.appointment_id, w.walk_in,
                w.plate AS plate, w.car_type AS car_type, u.name AS client_name, u.surname AS client_surname,
                cc.name AS company, w.mode AS detail, NULL AS detail_count, w.full_price, w.to_pay");
    }

    private static function filtered(Builder $query, array $filters): Builder
    {
        if ($from = self::text($filters, 'date_from')) {
            $query->whereDate('w.created_at', '>=', $from);
        }
        if ($to = self::text($filters, 'date_to')) {
            $query->whereDate('w.created_at', '<=', $to);
        }
        if ($washId = self::int($filters, 'car_wash_id')) {
            $query->where('w.car_wash_id', $washId);
        }
        if ($managerId = self::int($filters, 'manager_id')) {
            $query->where('w.manager_id', $managerId);
        }
        if ($type = self::text($filters, 'car_type')) {
            $query->where($query->from === 'package_washes as w' ? 'p.car_type' : 'w.car_type', $type);
        }
        if (($plate = self::text($filters, 'plate')) && ($plate = FleetCars::plate($plate)) !== '') {
            $column = $query->from === 'package_washes as w' ? 'c.plate' : 'w.plate';
            $query->where($column, 'like', '%'.addcslashes($plate, '%_\\').'%');
        }

        return $query;
    }

    private static function text(array $filters, string $key): ?string
    {
        $value = trim((string) ($filters[$key] ?? ''));

        return $value === '' ? null : $value;
    }

    private static function int(array $filters, string $key): ?int
    {
        $value = self::text($filters, $key);

        return $value !== null && ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
    }
}
