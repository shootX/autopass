<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Jobs\SendFleetInvite;
use App\Jobs\SendSms;
use App\Models\Appointment;
use App\Models\CarModel;
use App\Models\CorporateClient;
use App\Models\FleetCar;
use App\Models\User;
use App\Models\UserCar;
use App\Models\UserPackage;
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

    /** activation_error while the driver's own app car waits for their yes in the app. */
    public const AWAITING_DRIVER = 'driver_consent';

    /** activation_error after the driver said no; a new invite from the company asks again. */
    public const DECLINED = 'driver_declined';

    /** @return 'sent'|'no_phone'|'blocked'|'too_soon' */
    public static function invite(FleetCar $car): string
    {
        $result = DB::transaction(function () use ($car) {
            $locked = FleetCar::query()->whereKey($car->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === FleetCar::STATUS_BLOCKED) {
                return 'blocked';
            }
            if ($locked->driver_phone === null) {
                return 'no_phone';
            }
            if ($locked->invited_at && $locked->invited_at->gt(now()->subSeconds(self::RESEND_SECONDS))) {
                return 'too_soon';
            }
            $locked->forceFill(['invited_at' => now()] + ($locked->activation_error === self::DECLINED ? ['activation_error' => null] : []))->save();
            $car->setRawAttributes($locked->getAttributes(), true);

            return 'sent';
        });
        if ($result !== 'sent') {
            return $result;
        }

        // Inviting again is asking again: a driver who said no is asked once more in the app.
        $company = (string) $car->client?->name;
        $content = __('admin.fleet_invite_sms', [
            'company' => $company,
            'plate' => FleetCars::formatPlate($car->plate),
            // The web panel is gone (2026-11-01): the driver signs in through the app.
            'url' => config('services.app_stores.ios') ?: rtrim((string) config('app.url'), '/'),
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
     * Sets or replaces the driver. The old driver loses the car at once: the company link and, when the phone
     * changes, the app car too. That app car goes to the new driver, with its running packages, the first time
     * they sign in (owner's rule, 2026-11-03). The new driver is invited.
     *
     * @return 'sent'|'no_phone'|'blocked'|'too_soon'|'phone_invalid'|'driver_consent'
     */
    public static function setDriver(FleetCar $car, ?string $phone): string
    {
        $phone = trim((string) $phone);
        $normalized = $phone === '' ? null : Phone::normalize($phone);
        if ($phone !== '' && $normalized === null) {
            return 'phone_invalid';
        }

        $result = DB::transaction(function () use ($car, $normalized) {
            CorporateClient::query()->whereKey($car->corporate_client_id)->lockForUpdate()->firstOrFail();
            $locked = FleetCar::query()->whereKey($car->id)->lockForUpdate()->firstOrFail();
            $car->setRawAttributes($locked->getAttributes(), true);
            if ($normalized === $car->driver_phone) {
                // Saving the same driver preserves ownership, consent, cooldown and the live code.
                return null;
            }
            if ($car->driver_consent_required) {
                return self::AWAITING_DRIVER;
            }
            $previous = $car->user_car_id ? UserCar::query()->find($car->user_car_id) : null;
            $car->forceFill([
                'driver_phone' => $normalized,
                'driver_consented_at' => null,
                'status' => $car->status === FleetCar::STATUS_BLOCKED ? FleetCar::STATUS_BLOCKED : FleetCar::STATUS_INVITED,
                'handover_user_car_id' => $car->user_car_id ?? $car->handover_user_car_id,
                'user_id' => null,
                'user_car_id' => null,
                'activation_error' => null,
                'activated_at' => null,
                'qr_code' => null,
                'qr_expires_at' => null,
            ])->save();
            if ($previous) {
                self::release($previous);
            }

            return null;
        });
        if ($result !== null) {
            return $result;
        }

        // Linking waits for the driver to sign in or open the app after the SMS, even when the
        // phone already has an account: the company alone can't turn a car active.
        $sent = self::invite($car);
        if ($sent === 'too_soon' && $car->driver_phone !== null) {
            // The car's resend window is still open (one SMS a minute, however often the phone changes): the
            // new driver's SMS goes out when it closes, and only if the phone is still theirs by then.
            SendFleetInvite::dispatch((int) $car->id, (string) $car->driver_phone)->delay(self::resendAt($car));
        }

        return $sent;
    }

    /** When the car's resend window closes. */
    public static function resendAt(FleetCar $car): \DateTimeInterface
    {
        $at = $car->invited_at?->copy()->addSeconds(self::RESEND_SECONDS + 1);

        return $at && $at->isFuture() ? $at : now();
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

    /**
     * The driver changed their sign-in phone (SMS-checked): the company cars linked to this account keep the
     * driver and show the new phone, so the company does not see a stale number or, by typing the new one,
     * hand the car over as if to someone else. Cars only invited to the old number stay as they are.
     */
    public static function phoneChanged(User $user, ?string $oldPhone): int
    {
        $phone = Phone::normalize($user->phone);
        if ($phone === null || $phone === Phone::normalize($oldPhone)) {
            return 0;
        }

        return FleetCar::query()->where('user_id', $user->id)->update(['driver_phone' => $phone, 'updated_at' => now()]);
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
            // A driver who said no isn't linked by signing in again; the company's next invite asks again.
            ->where(fn ($query) => $query->whereNull('activation_error')->orWhere('activation_error', '!=', self::DECLINED))
            ->get();

        $activated = 0;
        foreach ($cars as $car) {
            if (self::link($car, $user)) {
                $activated++;
            }
        }

        return $activated;
    }

    /**
     * Company cars waiting for this driver's yes: the plate is a car they already have in the app.
     *
     * @return list<array{id: int, company: string, plate: string, brand: ?string, model: ?string, year: ?int}>
     */
    public static function awaitingFor(User $user): array
    {
        // A refresh must discover invites created after sign-in, not depend on a previous /me request.
        self::activateFor($user);
        $phone = Phone::normalize($user->phone);
        if ($phone === null) {
            return [];
        }

        return FleetCar::query()
            ->with('client:id,name')
            ->where('driver_phone', $phone)
            ->where(fn ($query) => $query->where('driver_consent_required', true)
                ->orWhere(fn ($query) => $query->where('status', FleetCar::STATUS_INVITED)->where('activation_error', self::AWAITING_DRIVER)))
            ->where('status', '!=', FleetCar::STATUS_BLOCKED)
            ->orderBy('id')
            ->get()
            ->map(fn (FleetCar $car) => [
                'id' => (int) $car->id,
                'company' => (string) $car->client?->name,
                'plate' => FleetCars::formatPlate($car->plate),
                'brand' => $car->brand,
                'model' => $car->model,
                'year' => $car->year === null ? null : (int) $car->year,
            ])
            ->values()
            ->all();
    }

    /** The driver agreed in the app: their own car joins the company. The answer is null or the activation error. */
    public static function accept(FleetCar $car, User $user): ?string
    {
        return DB::transaction(function () use ($car, $user) {
            CorporateClient::query()->whereKey($car->corporate_client_id)->lockForUpdate()->firstOrFail();
            $locked = FleetCar::query()->whereKey($car->id)->lockForUpdate()->firstOrFail();
            if (! self::answerable($locked, $user)) {
                return 'invite_gone';
            }
            $car->setRawAttributes($locked->getAttributes(), true);
            $car->forceFill(['driver_consented_at' => now(), 'driver_consent_required' => false, 'status' => FleetCar::STATUS_INVITED])->save();
            if (self::link($car, $user)) {
                return null;
            }
            $error = $car->fresh()?->activation_error;

            return $error && $error !== self::AWAITING_DRIVER ? (string) $error : 'failed';
        });
    }

    /** Declining preserves the driver's car and every package, including a link predating consent. */
    public static function decline(FleetCar $car, User $user): bool
    {
        return DB::transaction(function () use ($car, $user) {
            CorporateClient::query()->whereKey($car->corporate_client_id)->lockForUpdate()->firstOrFail();
            $locked = FleetCar::query()->whereKey($car->id)->lockForUpdate()->firstOrFail();
            if (! self::answerable($locked, $user)) {
                return false;
            }
            $locked->forceFill(['activation_error' => self::DECLINED, 'driver_consent_required' => false,
                'status' => FleetCar::STATUS_INVITED, 'user_id' => null, 'user_car_id' => null,
                'handover_user_car_id' => null, 'driver_consented_at' => null,
                'qr_code' => null, 'qr_expires_at' => null])->save();

            return true;
        });
    }

    private static function answerable(FleetCar $car, User $user): bool
    {
        $phone = Phone::normalize($user->phone);

        return $phone !== null && $car->driver_phone === $phone
            && $car->status !== FleetCar::STATUS_BLOCKED
            && ($car->driver_consent_required || ($car->status === FleetCar::STATUS_INVITED && $car->activation_error === self::AWAITING_DRIVER));
    }

    /** The invite this user may answer: theirs by phone and still waiting for them. */
    public static function awaiting(User $user, int $id): ?FleetCar
    {
        $phone = Phone::normalize($user->phone);

        return $phone === null ? null : FleetCar::query()
            ->whereKey($id)
            ->where('driver_phone', $phone)
            ->where(fn ($query) => $query->where('driver_consent_required', true)
                ->orWhere(fn ($query) => $query->where('status', FleetCar::STATUS_INVITED)->where('activation_error', self::AWAITING_DRIVER)))
            ->where('status', '!=', FleetCar::STATUS_BLOCKED)
            ->first();
    }

    /** True while a company holds this app car; the driver can't remove it or change its plate. */
    public static function isCompanyCar(int $userCarId): bool
    {
        return FleetCar::query()->where('user_car_id', $userCarId)->exists();
    }

    private static function link(FleetCar $car, User $user): bool
    {
        if ($car->driver_consent_required) {
            return false;
        }
        $consented = $car->driver_consented_at !== null;
        // The catalog may be asked here (a model seen for the first time), so before the transaction: a failed
        // insert of a parallel request must not end it on Postgres.
        $handedOver = $car->handover_user_car_id
            && UserCar::onlyTrashed()->whereKey($car->handover_user_car_id)->where('plate', $car->plate)->exists();
        $new = $handedOver || UserCar::query()->where('plate', $car->plate)->exists() ? null
            : ['model_id' => self::modelId($car), 'trim' => self::trim($car)];

        try {
            $linked = DB::transaction(function () use ($car, $user, $new, $consented) {
                $userCar = UserCar::query()->where('plate', $car->plate)->lockForUpdate()->first();
                $handover = null;
                if (! $userCar && $car->handover_user_car_id) {
                    // The replaced driver's app car comes to this driver, with its running packages.
                    $userCar = $handover = UserCar::onlyTrashed()->whereKey($car->handover_user_car_id)->where('plate', $car->plate)
                        ->lockForUpdate()->first();
                }
                if ($userCar && ! $handover && (int) $userCar->user_id !== (int) $user->id) {
                    // Someone else has this plate in the app: an admin has to sort it out.
                    $car->forceFill(['activation_error' => 'plate_taken'])->save();

                    return false;
                }
                if ($userCar && ! $handover && ! $consented && (int) $userCar->id !== (int) $car->handover_user_car_id) {
                    // The driver's own car, maybe with packages they paid for: it joins the company only once they
                    // agree in the app, so naming a plate and a phone can't take someone's car and packages. (Their
                    // car coming back to the same company car after a re-save needs no new yes.)
                    $car->forceFill(['activation_error' => self::AWAITING_DRIVER])->save();

                    return false;
                }
                if ($userCar && $car->bodyConfirmed() && $userCar->bodyConfirmed() && $userCar->car_type !== $car->car_type) {
                    // One car with two confirmed body types: the company's price and the driver's packages would
                    // count different cars. An admin sets the right type on one of them; the next sign-in links it.
                    $car->forceFill(['activation_error' => 'body_conflict'])->save();

                    return false;
                }
                if ($handover) {
                    self::handOver($handover, $user);
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
                    'handover_user_car_id' => null,
                    'activation_error' => null,
                    'activated_at' => now(),
                ])->save();

                return $userCar;
            });
            if ($linked instanceof UserCar && $linked->bodyConfirmed() && ! $car->bodyConfirmed()
                && ($car->body_options === null || in_array($linked->car_type, $car->body_options, true))) {
                // The driver had confirmed this car's body type already: the company car takes it, unless it waits
                // for another type the company named (then a geoapi.ge re-check or an admin decides).
                app(CarBodyStatus::class)->save($car, $linked->bodyColumns(), 'system');
            }

            return $linked instanceof UserCar;
        } catch (QueryException $e) {
            // A parallel add of the same plate won: leave the car invited, the next login retries.
            Log::warning('fleet_activation_failed', ['car' => $car->id, 'exception' => $e::class]);

            return false;
        }
    }

    /**
     * Takes a replaced driver's app car out of their app: hidden with its history, its package codes void and
     * their bookings for it cancelled. The packages wait for the next driver (handOver).
     */
    private static function release(UserCar $userCar): void
    {
        UserPackage::query()->where('user_car_id', $userCar->id)->update(['qr_expires_at' => null]);
        Appointment::query()->where('car_id', $userCar->id)->whereIn('approved', AppointmentStatus::active())
            ->update(['approved' => AppointmentStatus::Cancelled->value]);
        $userCar->delete();
    }

    /**
     * The replaced driver's app car and its running packages now belong to $user. Packages that ended stay in the
     * old driver's history; a card kept for renewal stays with its owner, so renewal is off.
     */
    private static function handOver(UserCar $userCar, User $user): void
    {
        UserPackage::query()->where('user_car_id', $userCar->id)->where('end_date', '>', now())->withWashesLeft()
            ->update(['user_id' => $user->id, 'rectoken' => null, 'auto_renew' => false, 'qr_expires_at' => null]);
        $userCar->restore();
        $userCar->forceFill(['user_id' => $user->id])->save();
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
