<?php

namespace App\Http\Controllers;

use App\Models\BodyType;
use App\Services\CarBody;
use App\Services\CarCatalog;
use Illuminate\Http\Request;

/**
 * The car catalog for the add-car form of the company panel and of the admin's company page:
 * models of a brand, the years of a model, and the body types of a year.
 */
class FleetCatalogController extends Controller
{
    public function models(int $brand)
    {
        $models = app(CarCatalog::class)->models($brand);

        return response()->json(['models' => array_map(fn (array $row) => [
            'id' => (int) $row['id'], 'name' => (string) $row['name'], 'source' => (string) ($row['source'] ?? CarCatalog::LOCAL),
        ], $models ?? [])]);
    }

    public function years(Request $request)
    {
        $data = $this->pick($request);
        $years = app(CarCatalog::class)->modelYears($data['source'], $data['brand_id'], $data['model_id']);

        return response()->json(['years' => $years ?? CarCatalog::years()]);
    }

    /** One type: it is set on its own. Several: the form shows a body type field with them. */
    public function bodyTypes(Request $request)
    {
        $data = $this->pick($request) + $request->validate([
            'year' => ['required', 'integer', 'between:'.CarCatalog::MIN_YEAR.','.((int) now()->year + 1)],
        ]);
        $types = app(CarBody::class)->options($data['source'], $data['brand_id'], $data['model_id'], (int) $data['year'])['types'];

        return response()->json([
            'choose' => count($types) > 1,
            'body_types' => array_map(fn (string $key) => ['key' => $key, 'name' => BodyType::labelFor($key)], $types),
        ]);
    }

    /** @return array{source: string, brand_id: int, model_id: int} */
    private function pick(Request $request): array
    {
        $data = $request->validate([
            'source' => ['required', 'in:'.CarCatalog::LOCAL.','.CarCatalog::REMOTE],
            'brand_id' => ['required', 'integer', 'min:1'],
            'model_id' => ['required', 'integer', 'min:1'],
        ]);

        return ['source' => $data['source'], 'brand_id' => (int) $data['brand_id'], 'model_id' => (int) $data['model_id']];
    }
}
