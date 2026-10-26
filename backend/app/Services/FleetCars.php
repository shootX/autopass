<?php

namespace App\Services;

use App\Models\BodyType;
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
     * @return array{added: int, skipped: int, errors: list<array{row: int, plate: string, reason: string, body_types?: list<string>}>}
     */
    public static function importSheet(CorporateClient $client, array $rows, string $source): array
    {
        $added = 0;
        $errors = [];
        $header = self::headerMap($rows[0] ?? []);
        $start = $header === null ? 0 : 1;

        for ($i = $start; $i < count($rows); $i++) {
            $row = $rows[$i];
            $car = [];
            foreach (self::COLUMNS as $index => $field) {
                $car[$field] = (string) ($row[$header === null ? $index : ($header[$field] ?? -1)] ?? '');
            }

            $result = self::add($client, $car, $source, $bodyTypes);
            if ($result === 'added') {
                $added++;
            } elseif ($car['plate'] !== '' || $car['brand'] !== '' || $car['model'] !== '') {
                $errors[] = ['row' => $i + 1, 'plate' => self::plate($car['plate']), 'reason' => $result]
                    + (str_starts_with($result, 'body_type') ? ['body_types' => $bodyTypes] : []);
            }
        }

        return ['added' => $added, 'skipped' => count($errors), 'errors' => $errors];
    }

    /** Sheet columns in the order a sheet without a header row has them. */
    private const COLUMNS = ['plate', 'brand', 'model', 'year', 'body_type', 'phone'];

    /**
     * Adds a car and, when a driver phone is given, sends the driver the invite SMS. The brand and model are
     * found in the car catalog by name (or by the ids of the panel's lists), and the body type comes from the
     * year: a year with several body types (a sedan and a wagon) needs body_type, and so does a model the
     * catalog knows no body type of. $bodyTypes gets the types that fit.
     *
     * @param  array<string, mixed>  $row  plate, brand, model (or brand_id, model_id), year, body_type, phone
     * @return 'added'|'duplicate'|'invalid'|'unknown_model'|'year_invalid'|'body_type_required'|'body_type_invalid'|'phone_invalid'
     */
    public static function add(CorporateClient $client, array $row, string $source, ?array &$bodyTypes = null): string
    {
        $bodyTypes = [];
        $text = fn (string $key) => isset($row[$key]) && is_scalar($row[$key]) ? trim((string) $row[$key]) : '';
        $plate = self::plate($text('plate'));
        $phone = $text('phone');

        if (! self::validPlate($plate)) {
            return 'invalid';
        }
        if ($phone !== '' && Phone::normalize($phone) === null) {
            return 'phone_invalid';
        }

        $model = self::catalogModel($text('brand'), $text('model'), (int) $text('brand_id'), (int) $text('model_id'));
        if (! $model) {
            return 'unknown_model';
        }
        $year = $text('year');
        $years = ctype_digit($year) ? app(CarCatalog::class)->modelYears($model['source'], $model['brand_id'], $model['model_id']) : null;
        if (! ctype_digit($year) || (int) $year < CarCatalog::MIN_YEAR || (int) $year > (int) now()->year + 1
            || ($years !== null && ! in_array((int) $year, $years, true))) {
            return 'year_invalid';
        }

        $typed = $text('body_type');
        $body = app(CarBody::class)->pick($model['source'], $model['brand_id'], $model['model_id'], (int) $year,
            $typed === '' ? null : (BodyType::keyFor($typed) ?? $typed));
        $bodyTypes = $body['types'];
        if ($body['error'] !== null || $body['car_type'] === null) {
            return $body['error'] ?? 'body_type_required';
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
                'brand' => mb_substr($model['brand'], 0, 64),
                'model' => mb_substr($model['model'], 0, 64),
                'source' => $source,
                'year' => (int) $year,
                'car_type' => $body['car_type'],
                'catalog_source' => $model['source'],
                'catalog_brand_id' => $model['brand_id'],
                'catalog_model_id' => $model['model_id'],
                'generation_id' => $body['generation_id'],
            ]);
        } catch (QueryException) {
            return 'duplicate';
        }

        $car->setRelation('client', $client);
        FleetInvites::setDriver($car, $phone);

        return 'added';
    }

    /**
     * A brand and model of the car catalog (geoapi.ge, or our own list while it is down), by id or by name.
     *
     * @return array{source: string, brand_id: int, model_id: int, brand: string, model: string}|null
     */
    public static function catalogModel(string $brandName, string $modelName, int $brandId = 0, int $modelId = 0): ?array
    {
        $catalog = app(CarCatalog::class);
        $same = fn (string $name) => fn (array $row) => mb_strtolower((string) $row['name']) === mb_strtolower($name);
        $brands = collect($catalog->brands());
        $brand = $brandId > 0 ? $brands->firstWhere('id', $brandId) : ($brandName === '' ? null : $brands->first($same($brandName)));
        if (! $brand) {
            return null;
        }
        $models = collect($catalog->models((int) $brand['id']) ?? []);
        $model = $modelId > 0 ? $models->firstWhere('id', $modelId) : ($modelName === '' ? null : $models->first($same($modelName)));
        if (! $model) {
            return null;
        }

        return [
            'source' => (string) ($model['source'] ?? $brand['source'] ?? CarCatalog::LOCAL),
            'brand_id' => (int) $brand['id'],
            'model_id' => (int) $model['id'],
            'brand' => (string) $brand['name'],
            'model' => (string) $model['name'],
        ];
    }

    /** Form errors for an add() result other than 'added'. */
    public static function addError(string $result): array
    {
        return match ($result) {
            'duplicate' => ['plate' => __('admin.fleet_plate_taken')],
            'phone_invalid' => ['phone' => __('admin.fleet_phone_invalid')],
            'unknown_model' => ['model_id' => __('admin.fleet_reason_unknown_model')],
            'year_invalid' => ['year' => __('admin.fleet_reason_year_invalid')],
            'body_type_required', 'body_type_invalid' => ['body_type' => __('admin.fleet_reason_'.$result)],
            default => ['plate' => __('admin.fleet_plate_format')],
        };
    }

    public static function templateCsv(): string
    {
        return "\xEF\xBB\xBFნომერი,მარკა,მოდელი,წელი,ძარა,ტელეფონი\nAB123CD,Toyota,Corolla,2018,,555 12 34 56\n";
    }

    /**
     * @param  array<int, mixed>  $row
     * @return array{plate: int, brand: int, model: int, year?: int, body_type?: int, phone?: int}|null
     */
    private static function headerMap(array $row): ?array
    {
        $aliases = [
            'plate' => ['plate', 'ნომერი', 'номер', 'number'],
            'brand' => ['brand', 'მარკა', 'ბრენდი', 'марка'],
            'model' => ['model', 'მოდელი', 'модель'],
            'year' => ['year', 'წელი', 'გამოშვების წელი', 'год', 'год выпуска'],
            'body_type' => ['body', 'body type', 'body_type', 'ძარა', 'ძარის ტიპი', 'кузов', 'тип кузова'],
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

        if (! isset($map['plate'], $map['brand'], $map['model'])) {
            return null;
        }

        return $map;
    }
}
