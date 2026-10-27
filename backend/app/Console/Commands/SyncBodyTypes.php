<?php

namespace App\Console\Commands;

use App\Models\BodyType;
use App\Models\CarModel;
use App\Models\Package;
use App\Models\UserCar;
use App\Services\CarCatalog;
use Illuminate\Console\Command;

/**
 * Sets body types from the car catalog for cars and models added before it was automatic:
 * each car from the body name its generation came with (no catalog request), each catalog model
 * from the body types of its generations (one cached catalog request per model).
 */
class SyncBodyTypes extends Command
{
    protected $signature = 'cars:sync-body-types
        {--dry-run : Only report what would change}
        {--cars-only : Skip the catalog models, so no catalog request is made}';

    protected $description = 'Set car and catalog model body types from the car catalog (geoapi.ge)';

    public function handle(CarCatalog $catalog): int
    {
        $dry = (bool) $this->option('dry-run');

        $cars = 0;
        $unknown = [];
        // A confirmed body type is never rewritten here (cars:check-bodies confirms them).
        UserCar::query()->whereNotNull('body_type')->where('body_type', '!=', '')->where('body_status', '!=', 'confirmed')->orderBy('id')
            ->each(function (UserCar $car) use ($dry, &$cars, &$unknown) {
                $type = BodyType::fromCatalog($car->body_type);
                if ($type === null) {
                    $unknown[$car->body_type] = ($unknown[$car->body_type] ?? 0) + 1;
                }
                if ($type !== $car->car_type) {
                    $cars++;
                    $dry || $car->forceFill(['car_type' => $type])->save();
                }
            });
        $this->info(($dry ? 'Would set' : 'Set')." the body type of {$cars} car(s).");

        if (! $this->option('cars-only')) {
            $models = 0;
            $missed = 0;
            CarModel::query()->where('source', CarCatalog::REMOTE)->whereNotNull('external_model_id')->orderBy('id')
                ->each(function (CarModel $model) use ($catalog, $dry, &$models, &$missed) {
                    $type = $catalog->remoteModelType((int) $model->external_model_id);
                    if ($type === null) {
                        $missed++;

                        return;
                    }
                    if ($type !== $model->type) {
                        $this->line("  {$model->name}: {$model->type} → {$type}");
                        $models++;
                        $dry || $model->forceFill(['type' => $type])->save();
                    }
                });
            $this->info(($dry ? 'Would change' : 'Changed')." {$models} catalog model(s); {$missed} kept their type (no known body type or catalog unavailable).");
        }

        foreach ($unknown as $name => $count) {
            $this->warn("Not recognised: \"{$name}\" ({$count} car(s)); their body type stays unconfirmed.");
        }

        $priced = Package::query()->distinct()->pluck('car_type')->flip();
        // Every car's type, confirmed or still a hint for cars:check-bodies.
        UserCar::query()->with('model')->get()->map(fn (UserCar $car) => $car->car_type ?: $car->model?->type)->filter()->countBy()
            ->reject(fn ($count, $type) => $priced->has($type))
            ->each(fn ($count, $type) => $this->warn("No package for \"{$type}\" ({$count} car(s)): their owners cannot buy one."));

        return self::SUCCESS;
    }
}
