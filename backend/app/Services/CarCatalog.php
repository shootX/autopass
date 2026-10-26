<?php

namespace App\Services;

use App\Models\BodyType;
use App\Models\CarBrand;
use App\Models\CarModel;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class CarCatalog
{
    public const LOCAL = 'local';

    public const REMOTE = 'carapi';

    public const MIN_YEAR = 1950;

    public function brands(): array
    {
        try {
            return $this->remoteBrands();
        } catch (\Throwable $e) {
            Log::warning('CarAPI brands unavailable, using local catalog.', ['error' => $e->getMessage()]);

            return CarBrand::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (CarBrand $b) => ['id' => (int) $b->id, 'name' => (string) $b->name, 'slug' => Str::slug($b->name), 'source' => self::LOCAL])
                ->all();
        }
    }

    public function models(int $brandId): ?array
    {
        $brand = collect($this->brands())->firstWhere('id', $brandId);
        if (! $brand) {
            return null;
        }
        if (($brand['source'] ?? self::LOCAL) === self::LOCAL) {
            return $this->localModels((int) $brand['id']);
        }

        try {
            return $this->remoteModels($brand);
        } catch (\Throwable $e) {
            Log::warning('CarAPI models unavailable, using local catalog.', ['error' => $e->getMessage()]);

            // Remote brand id, remote down: we can't serve models without mixing id spaces.
            return [];
        }
    }

    private function localModels(int $localBrandId): array
    {
        return CarModel::query()->where('brand_id', $localBrandId)->orderBy('name')->get(['id', 'name'])
            ->map(fn (CarModel $m) => ['id' => (int) $m->id, 'name' => (string) $m->name, 'source' => self::LOCAL])->all();
    }

    /**
     * Map a catalog pick to a local car_models id. source=local: id is ours. source=carapi:
     * ids are CarAPI ids, verified against the remote catalog and upserted by (source, external id).
     */
    public function resolveModelId(string $source, int $modelId, ?int $brandId): ?int
    {
        if ($source === self::LOCAL) {
            // brand_id, when sent, must be the model's own local brand.
            $id = CarModel::query()->whereKey($modelId)
                ->when($brandId !== null, fn ($q) => $q->where('brand_id', $brandId))
                ->value('id');

            return $id ? (int) $id : null;
        }
        if ($source !== self::REMOTE || $brandId === null) {
            return null;
        }

        try {
            $brand = collect($this->remoteBrands())->firstWhere('id', $brandId);
            $model = $brand ? collect($this->remoteModels($brand))->firstWhere('id', $modelId) : null;
        } catch (\Throwable $e) {
            Log::warning('CarAPI unavailable while resolving model.', ['error' => $e->getMessage()]);

            // Offline: reuse only a mapping we already made, and only under the same external brand.
            $known = CarModel::query()
                ->where('car_models.source', self::REMOTE)
                ->where('car_models.external_model_id', $modelId)
                ->whereHas('brand', fn ($q) => $q->where('source', self::REMOTE)->where('external_brand_id', $brandId))
                ->value('car_models.id');

            return $known ? (int) $known : null;
        }
        if (! $brand || ! $model) {
            return null;
        }

        // A parallel request can insert the same row between lookup and insert; the unique index
        // (source, external_*_id) rejects ours and we read the winner's row. No outer transaction here,
        // so a failed INSERT does not poison the connection on Postgres.
        $localBrand = $this->firstOrCreateUnique(CarBrand::class,
            ['source' => self::REMOTE, 'external_brand_id' => $brandId],
            ['name' => mb_substr($brand['name'], 0, 64)],
        );
        $keys = ['source' => self::REMOTE, 'external_model_id' => $modelId];
        $local = CarModel::query()->where($keys)->first() ?? $this->firstOrCreateUnique(CarModel::class, $keys, [
            'name' => mb_substr($model['name'], 0, 64),
            'brand_id' => $localBrand->id,
            'type' => $this->remoteModelType($modelId) ?? self::defaultType(),
        ]);

        return (int) $local->id;
    }

    /**
     * Our body type key for a remote model, from the body types of its generations: the most common one,
     * the newest generation's on a tie. null when the catalog names none we know or is down.
     */
    public function remoteModelType(int $modelId): ?string
    {
        try {
            $rows = $this->remoteGenerations($modelId);
        } catch (\Throwable $e) {
            Log::warning('CarAPI generations unavailable for the body type.', ['exception' => $e::class]);

            return null;
        }
        $types = [];
        foreach ($rows as $row) {
            $type = BodyType::fromCatalog($row['body_type'] ?? null);
            if ($type === null) {
                continue;
            }
            $year = is_numeric($row['year_start'] ?? null) ? (int) $row['year_start'] : 0;
            $types[$type] ??= ['count' => 0, 'year' => 0];
            $types[$type]['count']++;
            $types[$type]['year'] = max($types[$type]['year'], $year);
        }
        uasort($types, fn (array $a, array $b) => [$b['count'], $b['year']] <=> [$a['count'], $a['year']]);

        return array_key_first($types);
    }

    /** The type a model gets when nothing better is known. */
    public static function defaultType(): string
    {
        return BodyType::query()->where('name', 'sedan')->value('name') ?? BodyType::query()->orderBy('id')->value('name') ?? 'sedan';
    }

    /**
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<T>  $class
     * @return T
     */
    private function firstOrCreateUnique(string $class, array $keys, array $values)
    {
        $found = $class::query()->where($keys)->first();
        if ($found) {
            return $found;
        }

        try {
            return $class::query()->create($keys + $values);
        } catch (UniqueConstraintViolationException $e) {
            return $class::query()->where($keys)->first() ?? throw $e;
        }
    }

    /** Selectable model years, newest first. */
    /** null means no published production years: the client must label the choice as manual. */
    public function modelYears(string $source, int $brandId, int $modelId): ?array
    {
        if ($source !== self::REMOTE || $brandId < 1 || $modelId < 1) {
            return null;
        }
        try {
            if (! $this->remoteModel($brandId, $modelId)) {
                return null;
            }
            $rows = $this->remoteGenerations($modelId);
            $years = [];
            foreach ($rows as $row) {
                $start = $row['year_start'] ?? $row['start_year'] ?? null;
                $end = $row['year_end'] ?? $row['end_year'] ?? null;
                if (! is_numeric($start) || (int) $start < self::MIN_YEAR || (int) $start > (int) now()->year + 1) {
                    continue;
                }
                if ($end !== null && (! is_numeric($end) || (int) $end < (int) $start)) {
                    continue;
                }
                $last = min($end === null ? (int) now()->year + 1 : (int) $end, (int) now()->year + 1);
                foreach (range((int) $start, $last) as $year) {
                    $years[$year] = $year;
                }
            }
            rsort($years);

            return $years === [] ? null : array_values($years);
        } catch (\Throwable $e) {
            Log::warning('CarAPI years unavailable.', ['exception' => $e::class]);

            return null;
        }
    }

    /** The remote model under a remote brand, or null when either is unknown. Throws when CarAPI is down. */
    public function remoteModel(int $brandId, int $modelId): ?array
    {
        $brand = collect($this->remoteBrands())->firstWhere('id', $brandId);

        return $brand ? collect($this->remoteModels($brand))->firstWhere('id', $modelId) : null;
    }

    /** Raw generation rows of a remote model, cached; shared by years and the generation picker. */
    public function remoteGenerations(int $modelId): array
    {
        return Cache::remember('carapi.generations.'.$modelId, 60 * 60 * 6,
            fn () => $this->pages('/models/'.$modelId.'/generations'));
    }

    public static function httpsUrl(mixed $url): ?string
    {
        return is_string($url) && str_starts_with($url, 'https://') ? $url : null;
    }

    public static function years(): array
    {
        return range((int) now()->year + 1, self::MIN_YEAR);
    }

    private function remoteBrands(): array
    {
        return Cache::remember('carapi.brands', 60 * 60 * 6, function () {
            return array_map(fn (array $row) => [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'slug' => (string) $row['slug'],
                'logo' => self::httpsUrl($row['logo_url_light'] ?? null),
                'source' => self::REMOTE,
            ], $this->pages('/brands'));
        });
    }

    private function remoteModels(array $brand): array
    {
        return Cache::remember('carapi.models.'.$brand['slug'], 60 * 60 * 6, function () use ($brand) {
            return array_map(fn (array $row) => [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'photo_url' => self::httpsUrl($row['photo_url'] ?? null),
                'source' => self::REMOTE,
            ], $this->pages('/brands/'.rawurlencode($brand['slug']).'/models'));
        });
    }

    /** Every row of a list endpoint. geoapi.ge serves up to 1000 rows a page, which keeps the monthly quota low. */
    public function pages(string $path, array $query = []): array
    {
        $all = [];
        $page = 1;
        do {
            $response = $this->request()->acceptJson()->get($this->base().$path, $query + ['page' => $page, 'per_page' => 1000]);

            if (! $response->successful()) {
                throw new RuntimeException('CarAPI HTTP '.$response->status());
            }

            $json = $response->json();
            $all = array_merge($all, $json['data'] ?? []);
            $total = (int) ($json['meta']['total'] ?? count($all));
            $page++;
        } while (count($all) < $total && $page <= 50);

        return $all;
    }

    /** A single-object endpoint; null on 404. */
    public function item(string $path): ?array
    {
        $response = $this->request()->acceptJson()->get($this->base().$path);
        if ($response->status() === 404) {
            return null;
        }
        if (! $response->successful()) {
            throw new RuntimeException('CarAPI HTTP '.$response->status());
        }

        return $response->json('data') ?? $response->json();
    }

    public function base(): string
    {
        $base = rtrim((string) config('services.carapi.url'), '/');
        if ($base === '' || (string) config('services.carapi.secret') === '') {
            throw new RuntimeException('CarAPI is not configured');
        }

        return $base;
    }

    /** The secret key stays on the server; clients never call CarAPI themselves. */
    public function request(): PendingRequest
    {
        return Http::withToken((string) config('services.carapi.secret'))->timeout(8);
    }
}
