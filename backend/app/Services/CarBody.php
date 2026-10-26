<?php

namespace App\Services;

use App\Models\BodyType;
use App\Models\CarModel;
use Illuminate\Support\Facades\Log;

/**
 * A car's body type from its brand, model and year. The catalog (geoapi.ge) generations built in that
 * year name the body types: one type is set on its own, several (a sedan and a wagon) are offered to
 * pick from. A catalog model is never asked about its generation; the newest one of the picked type
 * gives the car its generation name and photo.
 */
class CarBody
{
    public function __construct(private CarCatalog $catalog) {}

    /**
     * types: what may be picked, in the body type list's order; one type means there is nothing to pick.
     * known: false when the catalog names no type we know (or is down), so every type is offered.
     * generations: type => the newest generation of that type built in the year.
     *
     * @return array{types: list<string>, known: bool, generations: array<string, int>}
     */
    public function options(string $source, int $brandId, int $modelId, ?int $year): array
    {
        $generations = $source === CarCatalog::REMOTE ? $this->remote($brandId, $modelId, $year) : [];
        if ($generations !== []) {
            return ['types' => $this->ordered(array_keys($generations)), 'known' => true, 'generations' => $generations];
        }
        // An admin set the type of a local model.
        $type = $source === CarCatalog::LOCAL ? CarModel::query()->whereKey($modelId)->value('type') : null;
        if ($type) {
            return ['types' => [(string) $type], 'known' => true, 'generations' => []];
        }

        return ['types' => array_keys(BodyType::options()), 'known' => false, 'generations' => []];
    }

    /**
     * The car's type from the options and the user's pick. error: 'body_type_required' when the year has several
     * types and none of them was picked, 'body_type_invalid' when the pick is not one of them. car_type null
     * with no error: nothing known and nothing picked, so the car goes by its model's type.
     *
     * @return array{car_type: ?string, generation_id: ?int, types: list<string>, error: ?string}
     */
    public function pick(string $source, int $brandId, int $modelId, ?int $year, ?string $chosen): array
    {
        $options = $this->options($source, $brandId, $modelId, $year);
        $types = $options['types'];
        $chosen = $chosen === '' ? null : $chosen;
        $error = null;
        if (count($types) === 1) {
            $type = $types[0];
        } elseif ($chosen !== null) {
            $type = in_array($chosen, $types, true) ? $chosen : null;
            $error = $type === null ? 'body_type_invalid' : null;
        } else {
            $type = null;
            $error = $options['known'] ? 'body_type_required' : null;
        }

        return [
            'car_type' => $type,
            'generation_id' => $type !== null ? ($options['generations'][$type] ?? null) : null,
            'types' => $types,
            'error' => $error,
        ];
    }

    /** @return array<string, int> */
    private function remote(int $brandId, int $modelId, ?int $year): array
    {
        try {
            if (! $this->catalog->remoteModel($brandId, $modelId)) {
                return [];
            }
            $rows = $this->catalog->remoteGenerations($modelId);
        } catch (\Throwable $e) {
            Log::warning('CarAPI generations unavailable for the body type.', ['exception' => $e::class]);

            return [];
        }

        $rows = collect($rows)
            ->filter(fn (array $row) => $year === null || CarTrims::covers($row, $year))
            ->sortByDesc(fn (array $row) => is_numeric($row['year_start'] ?? null) ? (int) $row['year_start'] : 0);
        $generations = [];
        foreach ($rows as $row) {
            $type = BodyType::fromCatalog($row['body_type'] ?? null);
            if ($type !== null && isset($row['id'])) {
                $generations[$type] ??= (int) $row['id'];
            }
        }

        return $generations;
    }

    /** @param  list<string>  $types */
    private function ordered(array $types): array
    {
        $order = array_flip(array_keys(BodyType::options()));

        usort($types, fn (string $a, string $b) => ($order[$a] ?? PHP_INT_MAX) <=> ($order[$b] ?? PHP_INT_MAX));

        return $types;
    }
}
