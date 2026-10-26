<?php

namespace App\Services;

use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\CorporateClient;
use App\Models\FleetCar;
use App\Support\Phone;
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
     * @return array{added: int, skipped: int, errors: list<array{row: int, plate: string, reason: string}>}
     */
    public static function importSheet(CorporateClient $client, array $rows, string $source): array
    {
        $added = 0;
        $errors = [];
        $header = self::headerMap($rows[0] ?? []);
        $start = $header === null ? 0 : 1;

        for ($i = $start; $i < count($rows); $i++) {
            $row = $rows[$i];
            $cell = fn (string $field, int $fallback) => (string) ($row[$header === null ? $fallback : ($header[$field] ?? -1)] ?? '');
            $plate = $cell('plate', 0);
            $brand = $cell('brand', 1);
            $model = $cell('model', 2);
            $phone = $cell('phone', 3);

            $result = self::add($client, $plate, $brand, $model, $source, $phone);
            if ($result === 'added') {
                $added++;
            } elseif ($plate !== '' || $brand !== '' || $model !== '') {
                $errors[] = ['row' => $i + 1, 'plate' => self::plate($plate), 'reason' => $result];
            }
        }

        return ['added' => $added, 'skipped' => count($errors), 'errors' => $errors];
    }

    /**
     * Adds a car and, when a driver phone is given, sends the driver the invite SMS.
     *
     * @return 'added'|'duplicate'|'invalid'|'unknown_model'|'phone_invalid'
     */
    public static function add(CorporateClient $client, string $plate, string $brand, string $model, string $source, ?string $phone = null): string
    {
        $plate = self::plate($plate);
        $brandName = trim($brand);
        $modelName = trim($model);
        $phone = trim((string) $phone);

        if (! self::validPlate($plate)) {
            return 'invalid';
        }
        if ($phone !== '' && Phone::normalize($phone) === null) {
            return 'phone_invalid';
        }

        $brandRow = $brandName === '' ? null : CarBrand::query()->whereRaw('lower(name) = ?', [mb_strtolower($brandName)])->first();
        $modelRow = $brandRow
            ? CarModel::query()->where('brand_id', $brandRow->id)->whereRaw('lower(name) = ?', [mb_strtolower($modelName)])->first()
            : null;
        if (! $brandRow || ! $modelRow) {
            return 'unknown_model';
        }

        $taken = FleetCar::query()
            ->whereRaw("upper(replace(replace(plate, ' ', ''), '-', '')) = ?", [$plate])
            ->exists();
        if ($taken) {
            return 'duplicate';
        }

        try {
            $car = FleetCar::query()->create([
                'corporate_client_id' => $client->id,
                'plate' => $plate,
                'brand' => $brandRow->name,
                'model' => $modelRow->name,
                'source' => $source,
            ]);
        } catch (QueryException) {
            return 'duplicate';
        }

        $car->setRelation('client', $client);
        FleetInvites::setDriver($car, $phone);

        return 'added';
    }

    /** Form errors for an add() result other than 'added'. */
    public static function addError(string $result): array
    {
        return match ($result) {
            'duplicate' => ['plate' => __('admin.fleet_plate_taken')],
            'phone_invalid' => ['phone' => __('admin.fleet_phone_invalid')],
            'unknown_model' => ['model_id' => __('admin.fleet_reason_unknown_model')],
            default => ['plate' => __('admin.fleet_plate_format')],
        };
    }

    public static function templateCsv(): string
    {
        $model = CarModel::query()->with('brand')->orderBy('id')->first();

        return "\xEF\xBB\xBFნომერი,მარკა,მოდელი,ტელეფონი\nAB123CD,".($model?->brand?->name ?? 'Brand').','.($model?->name ?? 'Model').",555 12 34 56\n";
    }

    /**
     * @param  array<int, mixed>  $row
     * @return array{plate: int, brand: int, model: int, phone?: int}|null
     */
    private static function headerMap(array $row): ?array
    {
        $aliases = [
            'plate' => ['plate', 'ნომერი', 'номер', 'number'],
            'brand' => ['brand', 'მარკა', 'ბრენდი', 'марка'],
            'model' => ['model', 'მოდელი', 'модель'],
            'phone' => ['phone', 'ტელეფონი', 'მძღოლის ტელეფონი', 'телефон'],
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
