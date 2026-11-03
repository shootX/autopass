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
     * Adds every row it can and lists the rows it skipped with the reason.
     *
     * @param  array<int, array<int, mixed>>  $rows
     * @return array{added: int, skipped: int, errors: list<array{row: int, plate: string, reason: string}>}
     */
    public static function importSheet(CorporateClient $client, array $rows, string $source): array
    {
        $added = 0;
        $pending = 0;
        $errors = [];
        $header = self::headerMap($rows[0] ?? []);
        $start = $header === null ? 0 : 1;

        for ($i = $start; $i < count($rows); $i++) {
            $row = $rows[$i];
            $car = [];
            foreach (self::COLUMNS as $index => $field) {
                $car[$field] = (string) ($row[$header === null ? $index : ($header[$field] ?? -1)] ?? '');
            }

            $result = self::add($client, $car, $source, $body);
            if ($result === 'added') {
                $added++;
                $pending += $body['status'] === CarBody::CONFIRMED ? 0 : 1;
            } elseif ($car['plate'] !== '' || $car['brand'] !== '' || $car['model'] !== '') {
                $errors[] = ['row' => $i + 1, 'plate' => self::plate($car['plate']), 'reason' => $result];
            }
        }

        return ['added' => $added, 'skipped' => count($errors), 'errors' => $errors, 'pending' => $pending];
    }

    /** Sheet columns in the order a sheet without a header row has them. */
    private const COLUMNS = ['plate', 'brand', 'model', 'year', 'body_type', 'phone'];

    /**
     * Adds a car and, when a driver phone is given, sends the driver the invite SMS. The brand and model are
     * found in the catalog. The company gives a body type as a hint: one complete catalog type confirms
     * automatically, but an ambiguous/incomplete list waits for the driver to pick. Unknown types are refused;
     * unavailable catalog data leaves the car unconfirmed. No QR or package allocation precedes confirmation.
     *
     * @param  array<string, mixed>  $row  plate, brand, model (or brand_id, model_id), year, body_type, phone
     * @param  array{status: string, types: list<string>}|null  $body
     * @return 'added'|'duplicate'|'invalid'|'unknown_model'|'year_invalid'|'body_type_required'|'body_type_invalid'|'body_type_mismatch'|'phone_invalid'
     */
    public static function add(CorporateClient $client, array $row, string $source, ?array &$body = null): string
    {
        $body = ['status' => CarBody::UNKNOWN, 'types' => []];
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

        // The company always names the body type; geoapi.ge confirms it (below) and adds the generation.
        if ($text('body_type') === '') {
            return 'body_type_required';
        }
        $type = BodyType::keyFor($text('body_type'));
        $known = array_keys(BodyType::options());
        if ($type === null || ! in_array($type, $known, true)) {
            $body['types'] = $known;

            return 'body_type_invalid';
        }
        $options = app(CarBody::class)->options($model['source'], $model['brand_id'], $model['model_id'], (int) $year);
        // When geoapi.ge names every body this model had that year, the company picks one of those.
        if ($options['types'] !== [] && ! $options['incomplete'] && ! in_array($type, $options['types'], true)) {
            $body['types'] = $options['types'];

            return 'body_type_mismatch';
        }
        // The type is confirmed only when geoapi.ge lists it. With no list, an incomplete one without this type,
        // or geoapi.ge down, the car is saved but waits: no washes until a re-check finds the type or an admin
        // confirms one. The company's type stays as the hint for both.
        $listed = in_array($type, $options['types'], true);
        // Several possible bodies belong to the driver's decision, even when the company named one.
        $confirmed = $listed && $options['status'] === CarBody::CONFIRMED;
        $status = $confirmed ? CarBody::CONFIRMED : ($options['types'] === [] ? CarBody::UNKNOWN : CarBody::CHOOSE);
        $generations = $confirmed ? ($options['generations'][$type] ?? []) : [];
        $body = ['status' => $status, 'types' => $options['types']];

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
                'catalog_source' => $model['source'],
                'catalog_brand_id' => $model['brand_id'],
                'catalog_model_id' => $model['model_id'],
                'car_type' => $confirmed ? $type : null,
                'body_status' => $status,
                'body_options' => $options['types'] === [] ? null : $options['types'],
                'body_incomplete' => $options['incomplete'],
                'body_source' => $confirmed ? CarBody::BY_CATALOG : null,
                'body_requested' => $type,
                // Not checked while geoapi.ge is down, so cars:check-bodies tries again.
                'body_checked_at' => $options['unavailable'] ? null : now(),
                'generation_id' => count($generations) === 1 ? $generations[0] : null,
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

    /** What the panel says after a car is added: also when its body type still waits for confirmation. */
    public static function addedMessage(?array $body): string
    {
        return ($body['status'] ?? CarBody::CONFIRMED) === CarBody::CONFIRMED
            ? __('admin.fleet_car_added')
            : __('admin.fleet_car_added_pending');
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
            'body_type_mismatch' => ['body_type' => __('admin.fleet_reason_body_type_mismatch')],
            default => ['plate' => __('admin.fleet_plate_format')],
        };
    }

    public static function templateCsv(): string
    {
        return "\xEF\xBB\xBFნომერი,მარკა,მოდელი,წელი,ძარა,ტელეფონი\nAB123CD,Toyota,Corolla,2018,sedan,555 12 34 56\n";
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
