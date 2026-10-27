<?php

namespace App\Services;

use App\Models\BodyType;
use App\Models\CorporateClient;
use App\Models\CorporatePackage;
use App\Models\CorporateWash;
use App\Models\CorporateWashMove;
use App\Models\CorporateWashPool;
use App\Models\FleetCar;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * A company's wash pools, one per body type, and each car's share of its own type's pool. A pool counts
 * every wash of that type the company owns, the ones given to cars included: 100 sedan washes with 4 on
 * sedan X become 99 and 3 after one wash. washes_balance on the company is the sum of its pools.
 * Every write locks the company row first, so washes, credits and allocations never interleave.
 */
class CorporateWashes
{
    /**
     * The whole pool and, per body type, what the company owns, has given to cars and has free.
     *
     * @return array{total: int, allocated: int, free: int, types: array<string, array{total: int, allocated: int, free: int}>}
     */
    public static function balance(CorporateClient $client): array
    {
        $pools = CorporateWashPool::query()->where('corporate_client_id', $client->id)->pluck('balance', 'car_type');
        $allocated = self::allocatedByType($client->id);
        $types = [];
        foreach ($pools->keys()->merge(array_keys($allocated))->unique() as $type) {
            $total = (int) ($pools[$type] ?? 0);
            $given = $allocated[$type] ?? 0;
            if ($total !== 0 || $given !== 0) {
                $types[$type] = ['total' => $total, 'allocated' => $given, 'free' => max(0, $total - $given)];
            }
        }
        $order = array_flip(array_keys(BodyType::options()));
        uksort($types, fn (string $a, string $b) => ($order[$a] ?? PHP_INT_MAX) <=> ($order[$b] ?? PHP_INT_MAX));

        return [
            'total' => (int) $pools->sum(),
            'allocated' => array_sum($allocated),
            'free' => array_sum(array_column($types, 'free')),
            'types' => $types,
        ];
    }

    /** The newest ledger rows first, for the admin and the company panel. */
    public static function ledger(CorporateClient $client): LengthAwarePaginator
    {
        return $client->washMoves()
            ->with(['package:id,title', 'invoice:id,number', 'wash:id,car_wash_id', 'wash.washing:id,name'])
            ->orderByDesc('id')
            ->paginate(20, ['*'], 'moves')
            ->withQueryString();
    }

    /**
     * Admin only. A credit adds a paid package (to its body type's pool) or a manual amount; a correction may
     * be negative. A pool never drops below the washes already given to cars of its type.
     *
     * @param  'credit'|'correction'  $type
     * @return 'ok'|'zero'|'below_allocated'|'no_body_type'
     */
    public static function credit(CorporateClient $client, string $type, int $amount, ?CorporatePackage $package, ?string $note, int $adminId, ?int $invoiceId = null, ?string $carType = null): string
    {
        if ($amount === 0 || ($type === CorporateWashMove::TYPE_CREDIT && $amount < 0)) {
            return 'zero';
        }
        $carType = $package?->car_type ?: $carType;
        if ($carType === null || $carType === '') {
            return 'no_body_type';
        }

        return DB::transaction(function () use ($client, $type, $amount, $package, $note, $adminId, $invoiceId, $carType) {
            $locked = self::lockClient($client->id);
            $pool = self::lockPool($locked->id, $carType);
            $next = (int) $pool->balance + $amount;
            if ($next < (self::allocatedByType($locked->id)[$carType] ?? 0)) {
                return 'below_allocated';
            }

            $pool->forceFill(['balance' => $next])->save();
            $locked->forceFill(['washes_balance' => (int) $locked->washes_balance + $amount])->save();
            // Mirror the saved value without marking it dirty: a later save() of the caller's copy
            // must not write a stale balance over a parallel wash.
            $client->washes_balance = $locked->washes_balance;
            $client->syncOriginalAttribute('washes_balance');
            self::move($locked, $type, $amount, [
                'car_type' => $carType,
                'corporate_package_id' => $package?->id,
                'corporate_invoice_id' => $invoiceId,
                'actor' => 'admin',
                'actor_id' => $adminId,
                'note' => $note,
            ]);

            return 'ok';
        });
    }

    /**
     * Sets how many of its body type's washes one car may use. Taking washes back returns them to the pool.
     * The car's body type is kept on it from then on, so its washes always come from and go back to one pool.
     *
     * @param  'admin'|'company'|'api'|'driver'|'system'  $actor
     * @return 'ok'|'invalid'|'not_enough'|'no_body_type'
     */
    public static function allocate(FleetCar $car, int $washes, string $actor, ?int $actorId = null): string
    {
        if ($washes < 0 || $washes > 100000) {
            return 'invalid';
        }

        return DB::transaction(function () use ($car, $washes, $actor, $actorId) {
            $client = self::lockClient((int) $car->corporate_client_id);
            $locked = FleetCar::query()->whereKey($car->id)->lockForUpdate()->firstOrFail();
            // Washes go only to a car of a confirmed body type; given ones may always go back to their own pool.
            $carType = $locked->carType() ?? ($washes < (int) $locked->washes_left ? $locked->car_type : null);
            if ($carType === null) {
                return 'no_body_type';
            }
            $delta = $washes - (int) $locked->washes_left;
            if ($delta === 0) {
                return 'ok';
            }
            $pool = self::lockPool($client->id, $carType);
            if ($delta > 0 && $delta > (int) $pool->balance - (self::allocatedByType($client->id)[$carType] ?? 0)) {
                return 'not_enough';
            }

            $locked->forceFill(['washes_left' => $washes, 'car_type' => $carType])->save();
            $car->washes_left = $washes;
            $car->car_type = $carType;
            $car->syncOriginalAttribute('washes_left');
            $car->syncOriginalAttribute('car_type');
            self::move($client, CorporateWashMove::TYPE_ALLOCATE, $delta, [
                'car_type' => $carType,
                'fleet_car_id' => $locked->id,
                'plate' => $locked->plate,
                'car_balance_after' => $washes,
                'actor' => $actor,
                'actor_id' => $actorId,
            ]);

            return 'ok';
        });
    }

    /**
     * Washes mode, or discount mode at one of the company's active discount packages (check it with
     * CorporateDiscounts::find). Without a package the car keeps its own or gets the company's only one;
     * with none it can't wash until the company picks one.
     */
    public static function setMode(FleetCar $car, string $mode, ?CorporatePackage $package = null): bool
    {
        if (! in_array($mode, FleetCar::MODES, true)) {
            return false;
        }
        $package = $mode === FleetCar::MODE_DISCOUNT ? ($package ?? CorporateDiscounts::fallback($car)) : null;
        $car->forceFill(['mode' => $mode, 'corporate_package_id' => $package?->id])->save();
        $car->setRelation('discountPackage', $package);

        return true;
    }

    /** Deletes a car; its unused washes go back to the pool, and the ledger says so. */
    public static function deleteCar(FleetCar $car, string $actor, ?int $actorId = null): void
    {
        DB::transaction(function () use ($car, $actor, $actorId) {
            $client = self::lockClient((int) $car->corporate_client_id);
            $locked = FleetCar::query()->whereKey($car->id)->lockForUpdate()->first();
            if (! $locked) {
                return;
            }
            if ((int) $locked->washes_left > 0) {
                self::move($client, CorporateWashMove::TYPE_RELEASE, -(int) $locked->washes_left, [
                    'car_type' => $locked->car_type,
                    'plate' => $locked->plate,
                    'car_balance_after' => 0,
                    'actor' => $actor,
                    'actor_id' => $actorId,
                ]);
            }
            $locked->delete();
        });
    }

    /** Why this car can't wash right now, or null when it can. */
    public static function blocker(FleetCar $car): ?string
    {
        if ($car->client?->ban) {
            return 'company_blocked';
        }
        if ($car->status !== FleetCar::STATUS_ACTIVE || ! $car->user_car_id) {
            return 'car_blocked';
        }
        if (! $car->bodyConfirmed()) {
            return 'body_unconfirmed';
        }
        if ($car->mode === FleetCar::MODE_DISCOUNT) {
            // A discount car needs an active discount package with a row for its body type, and washes left this month.
            if (! CorporateDiscounts::forCar($car)) {
                return 'discount_not_ready';
            }

            return CorporateDiscounts::left($car) === 0 ? 'discount_limit' : null;
        }
        if ((int) $car->washes_left < 1 || self::poolBalance((int) $car->corporate_client_id, $car->carType()) < 1) {
            return 'no_washes';
        }

        return null;
    }

    /**
     * Records one wash. A wash-package car gives one wash to it and to the pool; a discount car
     * keeps the price, the percent and what the driver pays on site, and the pool stays as it is.
     *
     * @param  array{car_wash_id: int, manager_id: int, appointment_id: ?int, walk_in: bool}  $at
     * @return array{result: string, mode?: string, washes_left?: int, company_washes?: int, discount?: ?array}
     */
    public static function wash(FleetCar $car, array $at): array
    {
        return DB::transaction(function () use ($car, $at) {
            $client = self::lockClient((int) $car->corporate_client_id);
            $locked = FleetCar::query()->whereKey($car->id)->lockForUpdate()->firstOrFail();
            $locked->setRelation('client', $client)->load(['userCar.model', 'discountPackage.prices']);
            $blocker = self::blocker($locked);
            if ($blocker !== null) {
                return ['result' => $blocker];
            }

            $row = [
                'corporate_client_id' => $client->id,
                'fleet_car_id' => $locked->id,
                'plate' => $locked->plate,
                'mode' => $locked->mode,
                'user_id' => $locked->user_id,
                'car_type' => CorporateDiscounts::carType($locked),
            ] + $at;

            if ($locked->mode === FleetCar::MODE_DISCOUNT) {
                $discount = CorporateDiscounts::forCar($locked);
                CorporateWash::query()->create($row + [
                    'corporate_package_id' => $discount->corporate_package_id,
                    'full_price' => $discount->full_price,
                    'discount_percent' => $discount->percent,
                    'to_pay' => $discount->toPay(),
                ]);

                return [
                    'result' => 'ok',
                    'mode' => $locked->mode,
                    'washes_left' => (int) $locked->washes_left,
                    'company_washes' => self::poolBalance($client->id, $locked->carType()),
                    'discount' => CorporateDiscounts::payload($discount, $locked),
                ];
            }

            // A car with washes has its body type kept on it (allocate), so this is the pool they came from.
            $pool = self::lockPool($client->id, (string) $locked->carType());
            $pool->forceFill(['balance' => (int) $pool->balance - 1])->save();
            $client->forceFill(['washes_balance' => (int) $client->washes_balance - 1])->save();
            $locked->forceFill(['washes_left' => (int) $locked->washes_left - 1])->save();
            $wash = CorporateWash::query()->create($row);
            self::move($client, CorporateWashMove::TYPE_WASH, -1, [
                'car_type' => $locked->carType(),
                'fleet_car_id' => $locked->id,
                'plate' => $locked->plate,
                'car_balance_after' => (int) $locked->washes_left,
                'corporate_wash_id' => $wash->id,
                'actor' => 'manager',
                'actor_id' => $at['manager_id'],
            ]);

            return [
                'result' => 'ok',
                'mode' => $locked->mode,
                'washes_left' => (int) $locked->washes_left,
                'company_washes' => (int) $pool->balance,
                'discount' => null,
            ];
        });
    }

    private static function lockClient(int $id): CorporateClient
    {
        return CorporateClient::query()->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    /** Washes of one type the company owns; 0 for a type it has none of. */
    public static function poolBalance(int $clientId, ?string $carType): int
    {
        return $carType === null ? 0
            : (int) CorporateWashPool::query()->where('corporate_client_id', $clientId)->where('car_type', $carType)->value('balance');
    }

    private static function lockPool(int $clientId, string $carType): CorporateWashPool
    {
        $keys = ['corporate_client_id' => $clientId, 'car_type' => $carType];
        // The company row is locked already, so no parallel request can create the same pool.
        CorporateWashPool::query()->firstOrCreate($keys);

        return CorporateWashPool::query()->where($keys)->lockForUpdate()->firstOrFail();
    }

    /** @return array<string, int> washes given to cars, per body type */
    private static function allocatedByType(int $clientId): array
    {
        return FleetCar::query()->where('corporate_client_id', $clientId)->where('washes_left', '>', 0)->whereNotNull('car_type')
            ->groupBy('car_type')->selectRaw('car_type, SUM(washes_left) AS given')->pluck('given', 'car_type')
            ->map(fn ($given) => (int) $given)->all();
    }

    private static function move(CorporateClient $client, string $type, int $amount, array $fields): void
    {
        CorporateWashMove::query()->create([
            'corporate_client_id' => $client->id,
            'type' => $type,
            'amount' => $amount,
            // The pool of the row's body type after it; the whole pool for a row without one.
            'balance_after' => isset($fields['car_type']) ? self::poolBalance($client->id, $fields['car_type']) : (int) $client->washes_balance,
        ] + $fields);
    }
}
