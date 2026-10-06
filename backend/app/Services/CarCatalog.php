<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CarCatalog
{
    public function brands(): array
    {
        return Cache::remember('carapi.brands', 60 * 60 * 6, function () {
            return array_map(fn (array $row) => [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'slug' => (string) $row['slug'],
            ], $this->pages('/brands'));
        });
    }

    public function models(int $brandId): ?array
    {
        $brand = collect($this->brands())->firstWhere('id', $brandId);
        if (!$brand) {
            return null;
        }

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
