<?php

namespace App\Console\Commands;

use App\Models\FleetCar;
use App\Models\UserCar;
use App\Models\UserPackage;
use App\Services\CarBody;
use App\Services\CarBodyStatus;
use Illuminate\Console\Command;

/**
 * Asks geoapi.ge about cars whose body type is not confirmed: those that existed before body types were confirmed,
 * and with --unknown those geoapi.ge could not tell before. Only a report unless --apply. A type a car had before is
 * kept only when it is one of geoapi.ge's types for the car (a company car's only when the company gave it through
 * the catalog form, sheet or API). The report lists running packages and company washes the outcome touches.
 */
class CheckCarBodies extends Command
{
    protected $signature = 'cars:check-bodies
        {--apply : Save the outcome; without it nothing is written}
        {--unknown : Also cars geoapi.ge could not tell when they were checked before}
        {--limit=0 : At most this many cars of each kind}';

    protected $description = 'Check unconfirmed car body types against geoapi.ge (a report unless --apply)';

    public function handle(CarBody $body, CarBodyStatus $status): int
    {
        $apply = (bool) $this->option('apply');
        $counts = [];
        $notes = [];

        foreach ([UserCar::class, FleetCar::class] as $class) {
            $query = $class::query()->where('body_status', '!=', CarBody::CONFIRMED)->orderBy('id')
                ->where(fn ($q) => $this->option('unknown')
                    ? $q->whereNull('body_checked_at')->orWhere('body_status', CarBody::UNKNOWN)
                    : $q->whereNull('body_checked_at'));
            if ((int) $this->option('limit') > 0) {
                $query->limit((int) $this->option('limit'));
            }
            foreach ($query->get() as $car) {
                $options = $status->options($car);
                $hint = $car instanceof UserCar || $car->catalog_model_id ? $car->car_type : null;
                $decision = CarBody::decide($options, $hint);
                if ($decision['error'] !== null) {
                    $decision = CarBody::decide($options, null);
                }
                $outcome = $options['unavailable'] ? 'unavailable' : $decision['status'];
                $kind = $car instanceof UserCar ? 'app' : 'company';
                $counts[$kind][$outcome] = ($counts[$kind][$outcome] ?? 0) + 1;
                array_push($notes, ...$this->notes($car, $decision['car_type'], $outcome));

                if ($apply && ! $options['unavailable']) {
                    $attributes = $body->attributes($options, $decision, CarBody::BY_LEGACY, $car->year, false);
                    if ($car instanceof UserCar) {
                        unset($attributes['generation_id']);
                    }
                    $status->save($car, $attributes, 'system');
                }
            }
        }

        foreach (['app' => 'App cars', 'company' => 'Company cars'] as $kind => $title) {
            $row = $counts[$kind] ?? [];
            $this->line(sprintf('%s: confirmed %d, driver picks %d, unknown %d, geoapi.ge not answering %d', $title,
                $row[CarBody::CONFIRMED] ?? 0, $row[CarBody::CHOOSE] ?? 0, $row[CarBody::UNKNOWN] ?? 0, $row['unavailable'] ?? 0));
        }
        if ($notes !== []) {
            $this->newLine();
            $this->line('Needs a look:');
            foreach ($notes as $note) {
                $this->line('  '.$note);
            }
        }
        $this->newLine();
        $this->line($apply ? 'Saved. Cars geoapi.ge did not answer for stay unchecked; run again later.' : 'Nothing saved. Run with --apply to save.');

        return self::SUCCESS;
    }

    /** What the outcome means for a running package or a company car's washes. @return list<string> */
    private function notes(UserCar|FleetCar $car, ?string $type, string $outcome): array
    {
        $notes = [];
        if ($car instanceof UserCar) {
            $packages = UserPackage::query()->with('package')->where('user_car_id', $car->id)->where('end_date', '>', now())
                ->whereColumn('used_washes', '<', 'number_of_washes')->get();
            foreach ($packages as $package) {
                $bought = (string) $package->package?->car_type;
                if ($type === null) {
                    $notes[] = "{$car->plate}: running package ({$bought}), body {$outcome}; "
                        .($package->body_legacy ? 'the package keeps working, the app asks the driver.' : 'washes wait for the body type.');
                } elseif ($bought !== $type) {
                    $notes[] = "{$car->plate}: running package bought as {$bought}, geoapi.ge says {$type}; the package keeps working until it ends.";
                }
            }

            return $notes;
        }

        $washes = (int) $car->washes_left;
        if ($washes > 0 && $type !== null && $car->car_type && $car->car_type !== $type) {
            $notes[] = "{$car->plate} (company car): {$washes} {$car->car_type} washes go back to the company's {$car->car_type} pool; it is {$type}.";
        } elseif ($washes > 0 && $type === null) {
            $notes[] = "{$car->plate} (company car): {$washes} washes wait, body {$outcome}.";
        } elseif ($car->mode === FleetCar::MODE_DISCOUNT && $type === null) {
            $notes[] = "{$car->plate} (company car): discount washes wait, body {$outcome}.";
        }

        return $notes;
    }
}
