<?php

namespace App\Services;

use App\Jobs\SendSms;
use App\Models\CarModel;
use App\Models\FleetCar;
use App\Models\User;
use App\Models\UserCar;
use App\Services\Sms\SmsMessage;
use App\Support\Phone;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Ties a fleet car to its driver: the company names a phone, the driver gets an SMS, and the
 * car becomes active the first time that phone signs in to the app. Only then does the
 * company see the car's washes.
 */
class FleetInvites
{
    /** A resend inside this window is refused, so a button can't flood the driver. */
    public const RESEND_SECONDS = 60;

    /** @return 'sent'|'no_phone'|'blocked'|'too_soon' */
    public static function invite(FleetCar $car): string
    {
        if ($car->status === FleetCar::STATUS_BLOCKED) {
            return 'blocked';
        }
        if ($car->driver_phone === null) {
            return 'no_phone';
        }
        if ($car->invited_at && $car->invited_at->gt(now()->subSeconds(self::RESEND_SECONDS))) {
            return 'too_soon';
        }

        $car->forceFill(['invited_at' => now()])->save();
        $company = (string) $car->client?->name;
        $content = __('admin.fleet_invite_sms', [
            'company' => $company,
            'plate' => FleetCars::formatPlate($car->plate),
            'url' => rtrim((string) config('app.url'), '/').'/auth',
        ], 'ka');

        SendSms::dispatch(new SmsMessage(
            Phone::forSms($car->driver_phone),
            $content,
            'fleet_invite',
            'fi'.Str::lower(Str::random(18)),
        ));

        return 'sent';
    }

    /**
     * Sets or replaces the driver. The old driver loses the car at once and the new one is invited.
     *
     * @return 'sent'|'no_phone'|'blocked'|'too_soon'|'phone_invalid'
     */
    public static function setDriver(FleetCar $car, ?string $phone): string
    {
        $phone = trim((string) $phone);
        $normalized = $phone === '' ? null : Phone::normalize($phone);
        if ($phone !== '' && $normalized === null) {
            return 'phone_invalid';
        }

        $blocked = $car->status === FleetCar::STATUS_BLOCKED;
        $car->forceFill([
            'driver_phone' => $normalized,
            'status' => $blocked ? FleetCar::STATUS_BLOCKED : FleetCar::STATUS_INVITED,
            'user_id' => null,
            'user_car_id' => null,
            'activation_error' => null,
            'activated_at' => null,
            'invited_at' => null,
            // The old driver's QR stops working with the change.
            'qr_code' => null,
            'qr_expires_at' => null,
        ])->save();

        // Linking waits for the driver to sign in or open the app after the SMS, even when the
        // phone already has an account: the company alone can't turn a car active.
        return self::invite($car);
    }

    public static function setBlocked(FleetCar $car, bool $blocked): void
    {
        if ($blocked) {
            $car->forceFill([
                'status' => FleetCar::STATUS_BLOCKED,
                'blocked_at' => now(),
                'qr_code' => null,
                'qr_expires_at' => null,
            ])->save();

            return;
        }

        $car->forceFill([
            'status' => $car->user_car_id ? FleetCar::STATUS_ACTIVE : FleetCar::STATUS_INVITED,
            'blocked_at' => null,
        ])->save();
    }

    /** Activates every car this user's phone was invited to. Cheap when there is none. */
    public static function activateFor(User $user): int
    {
        // driver_phone is stored normalized; older accounts may keep the phone in another format.
        $phone = Phone::normalize($user->phone);
        if ($phone === null) {
            return 0;
        }

        $cars = FleetCar::query()
            ->where('driver_phone', $phone)
            ->where('status', FleetCar::STATUS_INVITED)
            ->get();

        $activated = 0;
        foreach ($cars as $car) {
            if (self::link($car, $user)) {
                $activated++;
            }
        }

        return $activated;
    }

    /** True while a company holds this app car; the driver can't remove it or change its plate. */
    public static function isCompanyCar(int $userCarId): bool
    {
        return FleetCar::query()->where('user_car_id', $userCarId)->exists();
    }

    private static function link(FleetCar $car, User $user): bool
    {
        try {
            return DB::transaction(function () use ($car, $user) {
                $userCar = UserCar::query()->where('plate', $car->plate)->lockForUpdate()->first();
                if ($userCar && (int) $userCar->user_id !== (int) $user->id) {
                    // Someone else has this plate in the app: an admin has to sort it out.
                    $car->forceFill(['activation_error' => 'plate_taken'])->save();

                    return false;
                }

                if (! $userCar) {
                    $modelId = self::modelId($car);
                    if ($modelId === null) {
                        $car->forceFill(['activation_error' => 'model_missing'])->save();

                        return false;
                    }
                    $userCar = UserCar::query()->create([
                        'user_id' => $user->id,
                        'model_id' => $modelId,
                        'plate' => $car->plate,
                    ]);
                }

                $car->forceFill([
                    'status' => FleetCar::STATUS_ACTIVE,
                    'user_id' => $user->id,
                    'user_car_id' => $userCar->id,
                    'activation_error' => null,
                    'activated_at' => now(),
                ])->save();

                return true;
            });
        } catch (QueryException $e) {
            // A parallel add of the same plate won: leave the car invited, the next login retries.
            Log::warning('fleet_activation_failed', ['car' => $car->id, 'exception' => $e::class]);

            return false;
        }
    }

    private static function modelId(FleetCar $car): ?int
    {
        $id = CarModel::query()
            ->join('car_brands', 'car_brands.id', '=', 'car_models.brand_id')
            ->whereRaw('lower(car_brands.name) = ?', [mb_strtolower($car->brand)])
            ->whereRaw('lower(car_models.name) = ?', [mb_strtolower($car->model)])
            ->value('car_models.id');

        return $id === null ? null : (int) $id;
    }
}
