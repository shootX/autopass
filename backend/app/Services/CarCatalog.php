<?php

namespace App\Services;

use App\Models\BodyType;
use App\Models\CarBrand;
use App\Models\CarModel;
use Illuminate\Database\UniqueConstraintViolationException;
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
        $local = $this->firstOrCreateUnique(CarModel::class,
            ['source' => self::REMOTE, 'external_model_id' => $modelId],
            [
                'name' => mb_substr($model['name'], 0, 64),
                'brand_id' => $localBrand->id,
                'type' => BodyType::query()->where('name', 'sedan')->value('name') ?? BodyType::query()->orderBy('id')->value('name') ?? 'sedan',
            ],
        );

        return (int) $local->id;
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
                'source' => self::REMOTE,
            ], $this->pages('/brands/'.rawurlencode($brand['slug']).'/models'));
        });
    }

    private function pages(string $path): array
    {
        $secret = (string) config('services.carapi.secret');
        $base = rtrim((string) config('services.carapi.url'), '/');
        if ($secret === '' || $base === '') {
            throw new RuntimeException('CarAPI is not configured');
        }

        $all = [];
        $page = 1;
        do {
            $response = Http::withToken($secret)
                ->acceptJson()
                ->timeout(8)
                ->get($base.$path, ['page' => $page, 'per_page' => 100]);

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
}
