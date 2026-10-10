<?php

namespace App\Services;

use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\CorporateClient;
use App\Models\FleetCar;
use Illuminate\Database\QueryException;

class FleetCars
{
    public static function plate(string $plate): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $plate) ?? '');
    }

    public static function validPlate(string $plate): bool
    {
        return (bool) preg_match('/^[A-Z]{2}[0-9]{3}[A-Z]{2}$/', self::plate($plate));
    }

    public static function formatPlate(string $plate): string
    {
        $plate = self::plate($plate);
        if (preg_match('/^([A-Z]{2})([0-9]{3})([A-Z]{2})$/', $plate, $parts)) {
            return $parts[1].' - '.$parts[2].' - '.$parts[3];
        }

        return $plate;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return array{added: int, skipped: int}
     */
    public static function importSheet(CorporateClient $client, array $rows, string $source): array
    {
        $added = 0;
        $skipped = 0;
        $header = self::headerMap($rows[0] ?? []);
        $start = $header === null ? 0 : 1;

        for ($i = $start; $i < count($rows); $i++) {
            $row = $rows[$i];
            if ($header === null) {
                $plate = (string) ($row[0] ?? '');
                $brand = (string) ($row[1] ?? '');
                $model = (string) ($row[2] ?? '');
            } else {
                $plate = (string) ($row[$header['plate']] ?? '');
                $brand = (string) ($row[$header['brand']] ?? '');
                $model = (string) ($row[$header['model']] ?? '');
            }

            $result = self::add($client, $plate, $brand, $model, $source);
            if ($result === 'added') {
                $added++;
            } elseif ($plate !== '' || $brand !== '' || $model !== '') {
                $skipped++;
            }
        }

        return ['added' => $added, 'skipped' => $skipped];
    }

    /**
     * @return 'added'|'duplicate'|'invalid'
     */
    public static function add(CorporateClient $client, string $plate, string $brand, string $model, string $source): string
    {
        $plate = self::plate($plate);
        $brandName = trim($brand);
        $modelName = trim($model);
        $brandRow = $brandName === '' ? null : CarBrand::query()->where('name', 'ilike', $brandName)->first();
        $modelRow = $brandRow
            ? CarModel::query()->where('brand_id', $brandRow->id)->where('name', 'ilike', $modelName)->first()
            : null;

        if (!self::validPlate($plate) || !$brandRow || !$modelRow) {
            return 'invalid';
        }

        $taken = FleetCar::query()
            ->whereRaw("upper(regexp_replace(plate, '[^A-Z0-9]', '', 'g')) = ?", [$plate])
            ->exists();
        if ($taken) {
            return 'duplicate';
        }

        try {
            FleetCar::query()->create([
                'corporate_client_id' => $client->id,
                'plate' => $plate,
                'brand' => $brandRow->name,
                'model' => $modelRow->name,
                'source' => $source,
            ]);
        } catch (QueryException) {
            return 'duplicate';
        }

        return 'added';
    }

    public static function templateCsv(): string
    {
        $model = CarModel::query()->with('brand')->orderBy('id')->first();

        return "\xEF\xBB\xBFნომერი,მარკა,მოდელი\nAB123CD,".($model?->brand?->name ?? 'Brand').','.($model?->name ?? 'Model')."\n";
    }

    /**
     * @param  array<int, mixed>  $row
     * @return array{plate: int, brand: int, model: int}|null
     */
    private static function headerMap(array $row): ?array
    {
        $aliases = [
            'plate' => ['plate', 'ნომერი', 'номер', 'number'],
            'brand' => ['brand', 'მარკა', 'ბრენდი', 'марка'],
            'model' => ['model', 'მოდელი', 'модель'],
        ];
        $map = [];
        foreach ($row as $index => $cell) {
            $name = mb_strtolower(trim((string) $cell));
            foreach ($aliases as $field => $names) {
                if (in_array($name, $names, true)) {
                    $map[$field] = $index;
                }
            }
        }

        if (!isset($map['plate'], $map['brand'], $map['model'])) {
            return null;
        }

        return $map;
    }
}
