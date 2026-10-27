<?php

namespace App\Services;

use App\Models\BodyType;
use App\Models\CorporateClient;
use App\Models\FleetCar;
use App\Models\UserCar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Confirms the body type of a saved car: the driver's (or company's) pick from geoapi.ge's types, an admin's
 * type for a car geoapi.ge can't tell, or a retry when geoapi.ge was down. A driver's app car and the company
 * car of the same plate share the confirmed type. A company car whose given washes were of another type
 * returns them to that type's pool first, so the company's washes never move between types.
 */
class CarBodyStatus
{
    private const FLEET_COLUMNS = ['car_type', 'body_status', 'body_options', 'body_incomplete', 'body_source', 'body_checked_at', 'generation_id'];

    public function __construct(private CarBody $body, private CarCatalog $catalog) {}

    /** What geoapi.ge says about a saved car now. */
    public function options(UserCar|FleetCar $car): array
    {
        $year = $car->year ? (int) $car->year : null;
        if ($car instanceof UserCar) {
            $model = $car->model()->with('brand')->first();
            $remote = $model && $model->source === CarCatalog::REMOTE && $model->external_model_id;
            if ($remote && $car->generation_id) {
                // The driver picked the generation and modification: they say more than the year does.
                return $this->body->trimOptions((int) $model->external_model_id, (int) $car->generation_id,
                    $car->modification_id ? (int) $car->modification_id : null);
            }

            return $remote
                ? $this->body->options(CarCatalog::REMOTE, (int) $model->brand?->external_brand_id, (int) $model->external_model_id, $year)
                : $this->body->options(CarCatalog::LOCAL, 0, (int) $car->model_id, $year);
        }
        if ($car->catalog_source && $car->catalog_model_id) {
            return $this->body->options($car->catalog_source, (int) $car->catalog_brand_id, (int) $car->catalog_model_id, $year);
        }
        // A company car added before the catalog ids were kept: geoapi.ge's model of the same names.
        try {
            $ids = $this->catalog->remoteIdsByName((string) $car->brand, (string) $car->model);
        } catch (\Throwable $e) {
            Log::warning('CarAPI unavailable for the body type.', ['exception' => $e::class]);

            return ['status' => CarBody::UNKNOWN, 'types' => [], 'incomplete' => false, 'unavailable' => true, 'catalog' => null, 'generations' => []];
        }

        return $this->body->options(CarCatalog::REMOTE, (int) ($ids['brand_id'] ?? 0), (int) ($ids['model_id'] ?? 0), $year);
    }

    /**
     * geoapi.ge's answer for a car as it stands in memory (another year not saved yet) and a pick.
     *
     * @return array{error: ?string, types: list<string>, attributes: array<string, mixed>}
     */
    public function resolveFor(UserCar|FleetCar $car, ?string $chosen, string $by): array
    {
        $options = $this->options($car);
        $decision = CarBody::decide($options, $chosen);

        return [
            'error' => $decision['error'],
            'types' => $options['types'],
            'attributes' => $decision['error'] === null ? $this->body->attributes($options, $decision, $by, $car->year, $car instanceof UserCar) : [],
        ];
    }

    /**
     * Confirms a picked type. A driver or company picks one of geoapi.ge's types (asked again, or the saved list
     * while it is down); an admin may confirm any type. @return 'ok'|'confirmed'|'body_type_invalid'
     */
    public function choose(UserCar|FleetCar $car, string $type, string $by, string $actor): string
    {
        if ($car->bodyConfirmed() && $by !== CarBody::BY_ADMIN) {
            return $car->car_type === $type ? 'ok' : 'confirmed';
        }
        $options = $this->options($car);
        if ($by === CarBody::BY_ADMIN) {
            if (! BodyType::query()->where('name', $type)->exists()) {
                return 'body_type_invalid';
            }
            $attributes = ['body_source' => CarBody::BY_ADMIN, 'body_checked_at' => now()]
                + $this->body->attributes($options, ['status' => CarBody::CONFIRMED, 'car_type' => $type], $by, $car->year, $car instanceof UserCar);
        } else {
            if ($options['status'] === CarBody::UNKNOWN && $car->body_options) {
                // geoapi.ge is down now: the types it named before.
                $options = ['status' => CarBody::CHOOSE, 'types' => $car->body_options, 'incomplete' => (bool) $car->body_incomplete,
                    'unavailable' => false, 'catalog' => null, 'generations' => []];
            }
            $decision = CarBody::decide($options, $type);
            if ($decision['error'] !== null || $decision['car_type'] !== $type) {
                return 'body_type_invalid';
            }
            $attributes = $this->body->attributes($options, $decision, $by, $car->year, $car instanceof UserCar);
        }
        $this->save($car, $attributes, $actor);

        return 'ok';
    }

    /**
     * Asks geoapi.ge again about a car not yet confirmed. $hint: a type the car had before this check existed;
     * taken only when it is one of geoapi.ge's types. @return string the new body status
     */
    public function recheck(UserCar|FleetCar $car, string $actor, ?string $hint = null): string
    {
        if ($car->bodyConfirmed()) {
            return CarBody::CONFIRMED;
        }
        $options = $this->options($car);
        $decision = CarBody::decide($options, $hint);
        if ($decision['error'] !== null) {
            $decision = CarBody::decide($options, null);
        }
        $this->save($car, $this->body->attributes($options, $decision, CarBody::BY_LEGACY, $car->year, $car instanceof UserCar), $actor);

        return $decision['status'];
    }

    /** Saves the columns and shares a confirmed type with the other car of the plate. */
    public function save(UserCar|FleetCar $car, array $attributes, string $actor): void
    {
        if ($car instanceof FleetCar) {
            $this->saveFleet($car, $attributes, $actor);
            $userCar = $car->user_car_id ? UserCar::query()->find($car->user_car_id) : null;
            if ($car->bodyConfirmed() && $userCar && ! $userCar->bodyConfirmed()) {
                $userCar->forceFill($car->bodyColumns())->save();
            }

            return;
        }

        $car->forceFill($attributes)->save();
        if (! $car->bodyConfirmed()) {
            return;
        }
        $fleet = FleetCar::query()->where('user_car_id', $car->id)->get()->reject(fn (FleetCar $fleet) => $fleet->bodyConfirmed());
        foreach ($fleet as $fleetCar) {
            $this->saveFleet($fleetCar, ['generation_id' => $fleetCar->generation_id] + $car->bodyColumns(), $actor);
        }
    }

    private function saveFleet(FleetCar $car, array $attributes, string $actor): void
    {
        $attributes = array_intersect_key($attributes, array_flip(self::FLEET_COLUMNS));
        DB::transaction(function () use ($car, $attributes, $actor) {
            CorporateClient::query()->whereKey($car->corporate_client_id)->lockForUpdate()->first();
            $locked = FleetCar::query()->whereKey($car->id)->lockForUpdate()->firstOrFail();
            $type = $attributes['car_type'] ?? null;
            if ((int) $locked->washes_left > 0) {
                if ($type !== null && $locked->car_type && $locked->car_type !== $type) {
                    // Washes given as another type go back to that type's pool; the company gives them again.
                    CorporateWashes::allocate($locked, 0, $actor);
                } elseif ($type === null) {
                    // Still unconfirmed: the given washes stay counted in their own type's pool.
                    unset($attributes['car_type']);
                }
            }
            $locked->forceFill($attributes)->save();
            $car->setRawAttributes($locked->getAttributes(), true);
        });
    }
}
