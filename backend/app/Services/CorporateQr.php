<?php

namespace App\Services;

use App\Models\FleetCar;
use App\Models\User;
use App\Support\ApproveGrant;
use Illuminate\Support\Str;

/** The driver's QR for a company car and what the manager sees after scanning it. */
class CorporateQr
{
    public const TTL_SECONDS = 300;

    public const GRANT_SCOPE = 'corporate-approve';

    /** The user's own car while it is an active car of a company fleet. */
    public static function driverCar(User $user, int $userCarId): ?FleetCar
    {
        return FleetCar::query()
            ->where('user_car_id', $userCarId)
            ->where('user_id', $user->id)
            ->where('status', FleetCar::STATUS_ACTIVE)
            ->with('client')
            ->first();
    }

    /** A fresh code replaces the previous one, so an old screenshot stops working. */
    public static function issue(FleetCar $car): string
    {
        $code = 'fc'.Str::random(22);
        $car->forceFill(['qr_code' => $code, 'qr_expires_at' => now()->addSeconds(self::TTL_SECONDS)])->save();

        return $code;
    }

    /** @return FleetCar|'expired'|null */
    public static function find(string $qr): FleetCar|string|null
    {
        if (! str_starts_with($qr, 'fc') || strlen($qr) > 32) {
            return null;
        }
        $car = FleetCar::query()->where('qr_code', $qr)
            ->with(['client', 'user:id,name,surname', 'userCar.model', 'discountPackage.prices'])
            ->first();
        if (! $car) {
            return null;
        }

        return $car->qr_expires_at && $car->qr_expires_at->isPast() ? 'expired' : $car;
    }

    /** The checkqr answer for a company car. Only an approvable car gets an approve_token. */
    public static function scan(FleetCar $car, int $managerId): array
    {
        $blocker = CorporateWashes::blocker($car);
        $token = $blocker === null
            ? ApproveGrant::issue(self::GRANT_SCOPE, ['manager_id' => $managerId, 'fleet_car_id' => (int) $car->id])
            : null;

        return [
            'success' => true,
            'kind' => 'corporate',
            'approve_token' => $token,
            'corporate' => [
                'id' => (int) $car->id,
                'company' => (string) $car->client?->name,
                'mode' => $car->mode,
                'plate' => $car->plate,
                'car_type' => $car->carType(),
                'user' => [
                    'name' => $car->user?->name,
                    'surname' => $car->user?->surname,
                ],
                'washes_left' => (int) $car->washes_left,
                // The washes of the car's own body type, the only ones it can wash from.
                'company_washes' => CorporateWashes::poolBalance((int) $car->corporate_client_id, $car->carType()),
                // Discount mode: the full price, the percent and what the driver pays on site.
                'discount' => $car->mode === FleetCar::MODE_DISCOUNT ? CorporateDiscounts::payload(CorporateDiscounts::forCar($car), $car) : null,
                'can_approve' => $blocker === null,
                'reason' => $blocker,
                'reason_text' => $blocker === null ? null : __('corporate.reason_'.$blocker),
            ],
        ];
    }
}
