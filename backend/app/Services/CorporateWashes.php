<?php

namespace App\Services;

use App\Models\CorporateClient;
use App\Models\CorporatePackage;
use App\Models\CorporateWash;
use App\Models\CorporateWashMove;
use App\Models\FleetCar;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * A company's wash pool and each car's share of it. The pool counts every wash the company owns,
 * the ones given to cars included: 100 in the pool and 4 on car X become 99 and 3 after one wash.
 * Every write locks the company row first, so washes, credits and allocations never interleave.
 */
class CorporateWashes
{
    /** @return array{total: int, allocated: int, free: int} */
    public static function balance(CorporateClient $client): array
    {
        $total = (int) CorporateClient::query()->whereKey($client->id)->value('washes_balance');
        $allocated = self::allocated($client->id);

        return ['total' => $total, 'allocated' => $allocated, 'free' => max(0, $total - $allocated)];
    }

    /** The newest ledger rows first, for the admin and the company panel. */
    public static function ledger(CorporateClient $client): LengthAwarePaginator
    {
        return $client->washMoves()
            ->with(['package:id,title', 'wash:id,car_wash_id', 'wash.washing:id,name'])
            ->orderByDesc('id')
            ->paginate(20, ['*'], 'moves')
            ->withQueryString();
    }

    /**
     * Admin only. A credit adds a paid package or a manual amount; a correction may be negative.
     * The pool never drops below the washes already given to cars.
     *
     * @param  'credit'|'correction'  $type
     * @return 'ok'|'zero'|'below_allocated'
     */
    public static function credit(CorporateClient $client, string $type, int $amount, ?CorporatePackage $package, ?string $note, int $adminId): string
    {
        if ($amount === 0 || ($type === CorporateWashMove::TYPE_CREDIT && $amount < 0)) {
            return 'zero';
        }

        return DB::transaction(function () use ($client, $type, $amount, $package, $note, $adminId) {
            $locked = self::lockClient($client->id);
            $next = (int) $locked->washes_balance + $amount;
            if ($next < self::allocated($locked->id)) {
                return 'below_allocated';
            }

            $locked->forceFill(['washes_balance' => $next])->save();
            // Mirror the saved value without marking it dirty: a later save() of the caller's copy
            // must not write a stale balance over a parallel wash.
            $client->washes_balance = $next;
            $client->syncOriginalAttribute('washes_balance');
            self::move($locked, $type, $amount, [
                'corporate_package_id' => $package?->id,
                'actor' => 'admin',
                'actor_id' => $adminId,
                'note' => $note,
            ]);

            return 'ok';
        });
    }

    /**
     * Sets how many of the pool's washes one car may use. Taking washes back returns them to the pool.
     *
     * @param  'admin'|'company'|'api'  $actor
     * @return 'ok'|'invalid'|'not_enough'
     */
    public static function allocate(FleetCar $car, int $washes, string $actor, ?int $actorId = null): string
    {
        if ($washes < 0 || $washes > 100000) {
            return 'invalid';
        }

        return DB::transaction(function () use ($car, $washes, $actor, $actorId) {
            $client = self::lockClient((int) $car->corporate_client_id);
            $locked = FleetCar::query()->whereKey($car->id)->lockForUpdate()->firstOrFail();
            $delta = $washes - (int) $locked->washes_left;
            if ($delta === 0) {
                return 'ok';
            }
            if ($delta > 0 && $delta > (int) $client->washes_balance - self::allocated($client->id)) {
                return 'not_enough';
            }

            $locked->forceFill(['washes_left' => $washes])->save();
            $car->washes_left = $washes;
            $car->syncOriginalAttribute('washes_left');
            self::move($client, CorporateWashMove::TYPE_ALLOCATE, $delta, [
                'fleet_car_id' => $locked->id,
                'plate' => $locked->plate,
                'car_balance_after' => $washes,
                'actor' => $actor,
                'actor_id' => $actorId,
            ]);

            return 'ok';
        });
    }

    public static function setMode(FleetCar $car, string $mode): bool
    {
        if (! in_array($mode, FleetCar::MODES, true)) {
            return false;
        }
        $car->forceFill(['mode' => $mode])->save();

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
        if ($car->mode === FleetCar::MODE_DISCOUNT) {
            // A discount car needs a row for its body type in the company's active discount package.
            return CorporateDiscounts::forCar($car) ? null : 'discount_not_ready';
        }
        if ((int) $car->washes_left < 1 || (int) $car->client?->washes_balance < 1) {
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
            $locked->setRelation('client', $client)->load('userCar.model');
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
                    'company_washes' => (int) $client->washes_balance,
                    'discount' => CorporateDiscounts::payload($discount),
                ];
            }

            $client->forceFill(['washes_balance' => (int) $client->washes_balance - 1])->save();
            $locked->forceFill(['washes_left' => (int) $locked->washes_left - 1])->save();
            $wash = CorporateWash::query()->create($row);
            self::move($client, CorporateWashMove::TYPE_WASH, -1, [
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
                'company_washes' => (int) $client->washes_balance,
                'discount' => null,
            ];
        });
    }

    private static function lockClient(int $id): CorporateClient
    {
        return CorporateClient::query()->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    private static function allocated(int $clientId): int
    {
        return (int) FleetCar::query()->where('corporate_client_id', $clientId)->sum('washes_left');
    }

    private static function move(CorporateClient $client, string $type, int $amount, array $fields): void
    {
        CorporateWashMove::query()->create([
            'corporate_client_id' => $client->id,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => (int) $client->washes_balance,
        ] + $fields);
    }
}
