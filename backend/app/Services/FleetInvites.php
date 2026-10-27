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
        // The catalog may be asked here (a model seen for the first time), so before the transaction: a failed
        // insert of a parallel request must not end it on Postgres.
        $new = UserCar::query()->where('plate', $car->plate)->exists() ? null
            : ['model_id' => self::modelId($car), 'trim' => self::trim($car)];

        try {
            $linked = DB::transaction(function () use ($car, $user, $new) {
                $userCar = UserCar::query()->where('plate', $car->plate)->lockForUpdate()->first();
                if ($userCar && (int) $userCar->user_id !== (int) $user->id) {
                    // Someone else has this plate in the app: an admin has to sort it out.
                    $car->forceFill(['activation_error' => 'plate_taken'])->save();

                    return false;
                }

                if (! $userCar) {
                    $modelId = $new['model_id'] ?? self::modelId($car);
                    if ($modelId === null) {
                        $car->forceFill(['activation_error' => 'model_missing'])->save();

                        return false;
                    }
                    // The company car's body status comes along: confirmed, or the types the driver picks from.
                    $userCar = UserCar::query()->create([
                        'user_id' => $user->id,
                        'model_id' => $modelId,
                        'plate' => $car->plate,
                        'year' => $car->year,
                    ] + $car->bodyColumns() + ($new['trim'] ?? []));
                } elseif ($car->bodyConfirmed() && ! $userCar->bodyConfirmed()) {
                    // The driver's own car had no confirmed body type yet: take the one confirmed for the company car.
                    $userCar->forceFill(['year' => $userCar->year ?? $car->year] + $car->bodyColumns())->save();
                }

                $car->forceFill([
                    'status' => FleetCar::STATUS_ACTIVE,
                    'user_id' => $user->id,
                    'user_car_id' => $userCar->id,
                    'activation_error' => null,
                    'activated_at' => now(),
                ])->save();

                return $userCar;
            });
            if ($linked instanceof UserCar && $linked->bodyConfirmed() && ! $car->bodyConfirmed()
                && ($car->body_options === null || in_array($linked->car_type, $car->body_options, true))) {
                // The driver had confirmed this car's body type already: the company car takes it.
                app(CarBodyStatus::class)->save($car, $linked->bodyColumns(), 'system');
            }

            return $linked instanceof UserCar;
        } catch (QueryException $e) {
            // A parallel add of the same plate won: leave the car invited, the next login retries.
            Log::warning('fleet_activation_failed', ['car' => $car->id, 'exception' => $e::class]);

            return false;
        }
    }

    private static function modelId(FleetCar $car): ?int
    {
        if ($car->catalog_source && $car->catalog_model_id) {
            $id = app(CarCatalog::class)->resolveModelId($car->catalog_source, (int) $car->catalog_model_id, (int) $car->catalog_brand_id ?: null);
            if ($id !== null) {
                return $id;
            }
        }
        // A car added before the catalog ids were kept, or the catalog is down: the model by its name.
        $id = CarModel::query()
            ->join('car_brands', 'car_brands.id', '=', 'car_models.brand_id')
            ->whereRaw('lower(car_brands.name) = ?', [mb_strtolower($car->brand)])
            ->whereRaw('lower(car_models.name) = ?', [mb_strtolower($car->model)])
            ->value('car_models.id');

        return $id === null ? null : (int) $id;
    }

    /** The generation name and photo of the company's catalog car, best effort: the car is linked without them. */
    private static function trim(FleetCar $car): array
    {
        if ($car->catalog_source !== CarCatalog::REMOTE || ! $car->generation_id) {
            return [];
        }
        try {
            $trim = app(CarTrims::class)->attributes((int) $car->catalog_brand_id, (int) $car->catalog_model_id, (int) $car->generation_id, null);

            return array_filter(array_diff_key($trim, ['car_type' => true]), fn ($value) => $value !== null);
        } catch (\Throwable $e) {
            Log::warning('fleet_car_trim_unavailable', ['car' => $car->id, 'exception' => $e::class]);

            return [];
        }
    }
}
