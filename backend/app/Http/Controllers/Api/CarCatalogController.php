<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CarTrims;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/** Generation, modification, specs and photo of a CarAPI (geoapi.ge) model. Local models have none of these. */
class CarCatalogController extends Controller
{
    public function generations(Request $request, int $model_id, CarTrims $trims)
    {
        $data = $request->validate([
            'brand_id' => ['required', 'integer', 'min:1'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
        ]);

        return $this->answer('generations', fn () => $trims->generations((int) $data['brand_id'], $model_id, isset($data['year']) ? (int) $data['year'] : null));
    }

    public function modifications(Request $request, int $model_id, int $generation_id, CarTrims $trims)
    {
        $data = $request->validate(['year' => ['nullable', 'integer', 'min:1900', 'max:2100']]);

        return $this->answer('modifications', fn () => $trims->modifications($model_id, $generation_id, isset($data['year']) ? (int) $data['year'] : null));
    }

    public function modification(int $id, CarTrims $trims)
    {
        return $this->answer('modification', fn () => $trims->modification($id));
    }

    /** Public: image views send no token. Bytes come from our disk after the first upstream fetch. */
    public function photo(Request $request, int $model_id, CarTrims $trims)
    {
        $generation = $request->integer('generation') ?: null;
        try {
            $photo = $trims->photo($model_id, $generation);
        } catch (\Throwable $e) {
            Log::warning('car_photo_unavailable', ['exception' => $e::class]);

            return response()->json(['success' => false, 'error' => __('cars.catalog_unavailable')], 503);
        }
        if (! $photo) {
            return response()->json(['success' => false, 'error' => __('cars.photo_missing')], 404);
        }

        return response($photo['body'], 200, [
            'Content-Type' => $photo['type'],
            'Cache-Control' => 'public, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function answer(string $key, callable $load)
    {
        try {
            $result = $load();
        } catch (\Throwable $e) {
            Log::warning('car_catalog_unavailable', ['key' => $key, 'exception' => $e::class]);

            return response()->json(['success' => false, 'error' => __('cars.catalog_unavailable')], 503);
        }
        if ($result === null) {
            return response()->json(['success' => false, 'error' => __('cars.not_found')], 404);
        }

        return response()->json(['success' => true, $key => $result]);
    }
}
