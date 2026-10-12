<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use App\Models\CarBrand;
use App\Models\CarModel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class CarCatalog
{
    public function brands(): array
    {
        try {
            return $this->remoteBrands();
        } catch (\Throwable $e) {
            Log::warning('CarAPI brands unavailable, using local catalog.', ['error' => $e->getMessage()]);

            return CarBrand::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (CarBrand $b) => ['id' => (int) $b->id, 'name' => (string) $b->name, 'slug' => Str::slug($b->name)])
                ->all();
        }
    }

    public function models(int $brandId): ?array
    {
        $brand = collect($this->brands())->firstWhere('id', $brandId);
        if (!$brand) {
            return null;
        }

        try {
            return $this->remoteModels($brand);
        } catch (\Throwable $e) {
            Log::warning('CarAPI models unavailable, using local catalog.', ['error' => $e->getMessage()]);

            $local = CarBrand::query()->where('name', $brand['name'])->first();

            return $local
                ? CarModel::query()->where('brand_id', $local->id)->orderBy('name')->get(['id', 'name'])
                    ->map(fn (CarModel $m) => ['id' => (int) $m->id, 'name' => (string) $m->name])->all()
                : [];
        }
    }

    private function remoteBrands(): array
    {
        return Cache::remember('carapi.brands', 60 * 60 * 6, function () {
            return array_map(fn (array $row) => [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'slug' => (string) $row['slug'],
            ], $this->pages('/brands'));
        });
    }

    private function remoteModels(array $brand): array
    {
        return Cache::remember('carapi.models.'.$brand['slug'], 60 * 60 * 6, function () use ($brand) {
            return array_map(fn (array $row) => [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
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

            if (!$response->successful()) {
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
