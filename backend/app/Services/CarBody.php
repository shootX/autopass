<?php

namespace App\Services;

use App\Models\BodyType;
use App\Models\CarModel;
use Illuminate\Support\Facades\Log;

/**
 * A car's body type from its brand, model and year, as geoapi.ge names it, and never guessed. The generations
 * built in that year name the body types: one type (every generation naming one we know) is confirmed on its own;
 * several, or one next to generations that name none we know, wait for a person to pick one of them; none, or
 * geoapi.ge down, leaves the type unknown until a retry. A generation, modification or generation photo is kept
 * only when exactly one fits; a model's general photo says nothing about the generation or body.
 */
class CarBody
{
    public const CONFIRMED = 'confirmed';

    public const CHOOSE = 'choose';

    public const UNKNOWN = 'unknown';

    /** Who confirmed the type: geoapi.ge on its own, or who picked it from geoapi.ge's list. */
    public const BY_CATALOG = 'catalog';

    public const BY_DRIVER = 'driver';

    public const BY_COMPANY = 'company';

    public const BY_ADMIN = 'admin';

    public const BY_LEGACY = 'legacy';

    private const TRIM = ['generation_id', 'generation_name', 'modification_id', 'modification_name', 'body_type', 'photo'];

    public function __construct(private CarCatalog $catalog, private CarTrims $trims) {}

    /**
     * What geoapi.ge says about this model in this year (every year when null). A local model (our own list,
     * or geoapi.ge was down when the car was added) is looked up in geoapi.ge by its brand and model names.
     * types: in the body type list's order. incomplete: a generation of that year names no type we know.
     * unavailable: geoapi.ge did not answer. generations: type => ids of that type's generations of the year.
     *
     * @return array{status: string, types: list<string>, incomplete: bool, unavailable: bool, catalog: ?array{brand_id: int, model_id: int}, generations: array<string, list<int>>}
     */
    public function options(string $source, int $brandId, int $modelId, ?int $year): array
    {
        $result = ['status' => self::UNKNOWN, 'types' => [], 'incomplete' => false, 'unavailable' => false, 'catalog' => null, 'generations' => []];
        try {
            $ids = $this->catalogIds($source, $brandId, $modelId);
            $rows = $ids === null ? [] : $this->catalog->remoteGenerations($ids['model_id']);
        } catch (\Throwable $e) {
            Log::warning('CarAPI unavailable for the body type.', ['exception' => $e::class]);

            return ['unavailable' => true] + $result;
        }

        $generations = [];
        $incomplete = false;
        foreach ($rows as $row) {
            if ($year !== null && ! CarTrims::covers($row, $year)) {
                continue;
            }
            $type = BodyType::fromCatalog($row['body_type'] ?? null);
            if ($type === null || ! isset($row['id'])) {
                // An empty or unknown body name: one known type next to it does not prove the car is that type.
                $incomplete = true;

                continue;
            }
            $generations[$type][] = (int) $row['id'];
        }
        $types = $this->ordered(array_keys($generations));

        return [
            'status' => match (true) {
                $types === [] => self::UNKNOWN,
                count($types) === 1 && ! $incomplete => self::CONFIRMED,
                default => self::CHOOSE,
            },
            'types' => $types,
            'incomplete' => $incomplete,
            'unavailable' => false,
            'catalog' => $ids,
            'generations' => $generations,
        ];
    }

    /**
     * The car's type from the options and a pick. A pick must be one of geoapi.ge's types ('body_type_invalid'
     * otherwise); with nothing to check it against (unknown) it is not taken.
     *
     * @return array{status: string, car_type: ?string, error: ?string}
     */
    public static function decide(array $options, ?string $chosen): array
    {
        $chosen = $chosen === '' ? null : $chosen;
        if ($options['status'] === self::UNKNOWN) {
            return ['status' => self::UNKNOWN, 'car_type' => null, 'error' => null];
        }
        if ($chosen !== null && ! in_array($chosen, $options['types'], true)) {
            return ['status' => $options['status'], 'car_type' => null, 'error' => 'body_type_invalid'];
        }
        if ($options['status'] === self::CONFIRMED) {
            return ['status' => self::CONFIRMED, 'car_type' => $options['types'][0], 'error' => null];
        }

        return $chosen === null
            ? ['status' => self::CHOOSE, 'car_type' => null, 'error' => null]
            : ['status' => self::CONFIRMED, 'car_type' => $chosen, 'error' => null];
    }

    /**
     * Asks geoapi.ge and takes the pick: the columns to save, or the error with the types that fit.
     * $details: also the generation, modification and photo (a driver's car; a company car keeps only the type).
     *
     * @return array{error: ?string, types: list<string>, options: array, attributes: array<string, mixed>}
     */
    public function resolve(string $source, int $brandId, int $modelId, ?int $year, ?string $chosen, string $by, bool $details = true): array
    {
        $options = $this->options($source, $brandId, $modelId, $year);
        $decision = self::decide($options, $chosen);

        return [
            'error' => $decision['error'],
            'types' => $options['types'],
            'options' => $options,
            'attributes' => $decision['error'] === null ? $this->attributes($options, $decision, $by, $year, $details) : [],
        ];
    }

    /** The body columns (and with $details the trim columns) for a decision on these options. */
    public function attributes(array $options, array $decision, string $by, ?int $year, bool $details = true): array
    {
        $type = $decision['car_type'];
        $ids = $type === null ? [] : ($options['generations'][$type] ?? []);
        $generation = count($ids) === 1 ? $ids[0] : null;
        $body = [
            'car_type' => $type,
            'body_status' => $decision['status'],
            'body_options' => $options['types'] === [] ? null : $options['types'],
            'body_incomplete' => $options['incomplete'],
            'body_source' => $type === null ? null : ($options['status'] === self::CONFIRMED ? self::BY_CATALOG : $by),
            // Not checked while geoapi.ge is down, so cars:check-bodies tries again.
            'body_checked_at' => $options['unavailable'] ? null : now(),
        ];

        return $details ? $body + $this->trim($options['catalog'], $generation, $year) : $body + ['generation_id' => $generation];
    }

    /** All body types as rows for the apps. @param list<string> $types */
    public static function rows(array $types): array
    {
        return array_map(fn (string $key) => ['key' => $key, 'name' => BodyType::labelFor($key)], $types);
    }

    /** @return array{brand_id: int, model_id: int}|null */
    private function catalogIds(string $source, int $brandId, int $modelId): ?array
    {
        if ($source === CarCatalog::REMOTE) {
            return $this->catalog->remoteModel($brandId, $modelId) ? ['brand_id' => $brandId, 'model_id' => $modelId] : null;
        }
        $model = CarModel::query()->with('brand')->find($modelId);
        if (! $model) {
            return null;
        }
        if ($model->source === CarCatalog::REMOTE && $model->external_model_id && $model->brand?->external_brand_id) {
            return $this->catalogIds(CarCatalog::REMOTE, (int) $model->brand->external_brand_id, (int) $model->external_model_id);
        }

        return $this->catalog->remoteIdsByName((string) $model->brand?->name, (string) $model->name);
    }

    /** Generation, modification and photo, each only when exactly one fits; best effort, a car is saved without them. */
    private function trim(?array $catalog, ?int $generationId, ?int $year): array
    {
        $empty = array_fill_keys(self::TRIM, null);
        if ($catalog === null) {
            return $empty;
        }
        $modification = null;
        if ($generationId && $year) {
            try {
                $modifications = $this->trims->modifications($catalog['model_id'], $generationId, $year) ?? [];
                $modification = count($modifications) === 1 ? (int) $modifications[0]['id'] : null;
            } catch (\Throwable $e) {
                Log::warning('car_modifications_unavailable', ['exception' => $e::class]);
            }
        }
        try {
            $trim = $this->trims->attributes($catalog['brand_id'], $catalog['model_id'], $generationId, $modification);

            return array_intersect_key($trim, $empty) + $empty;
        } catch (\Throwable $e) {
            Log::warning('car_trim_unavailable', ['exception' => $e::class]);

            return $empty;
        }
    }

    /** @param  list<string>  $types */
    private function ordered(array $types): array
    {
        $order = array_flip(array_keys(BodyType::options()));

        usort($types, fn (string $a, string $b) => ($order[$a] ?? PHP_INT_MAX) <=> ($order[$b] ?? PHP_INT_MAX));

        return $types;
    }
}
