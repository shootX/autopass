<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BodyType;
use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\UserCar;
use App\Services\CarCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CarsController extends Controller
{
    public function brands(Request $request)
    {
        try {
            $brands = array_map(fn (array $brand) => [
                'id' => $brand['id'],
                'name' => $brand['name'],
                'source' => $brand['source'],
            ], app(CarCatalog::class)->brands());

            return response()->json(['success' => true, 'brands' => $brands]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => 'Ошибка'], 400);
        }
    }

    public function models(Request $request, $brand_id)
    {
        try {
            $catalog = app(CarCatalog::class);
            $models = $catalog->models((int) $brand_id);
            if ($models === null) {
                return response()->json(['success' => false, 'error' => 'Ошибка'], 404);
            }

            $brand = collect($catalog->brands())->firstWhere('id', (int) $brand_id);

            return response()->json([
                'success' => true,
                'brand' => ['id' => (int) $brand_id, 'name' => $brand['name'] ?? '', 'source' => $brand['source'] ?? CarCatalog::LOCAL],
                'models' => $models,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => 'Ошибка'], 400);
        }
    }

    public function mycars(Request $request)
    {
        try {
            $user = auth()->user();

            $cars = $user->cars()->with(['model.brand'])->get();

            $cars = $cars->map(function ($car) {
                $m = $car->model;
                $b = $m->brand;

                return [
                    'id' => $car->id,
                    'plate' => $car->plate,
                    'year' => $car->year,
                    'model' => $m,
                    'brand' => $b,
                    'image' => $m->image,
                ];
            });

            return response()->json([
                'success' => true,
                'cars' => $cars,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Request failed',
            ], 400);
        }
    }

    public function addMyCar(Request $request)
    {
        $user = auth()->user();

        $validator = Validator::make($request->all(), [
            'plate' => ['required', 'string', 'max:9', function ($attribute, $value, $fail) {
                if (! preg_match('/^[A-Z]{2}[0-9]{3}[A-Z]{2}$/', $value)) {
                    $fail('The '.$attribute.' must be in the format of 2 letters 3 numbers 2 letters.');
                }
            }],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Не верный формат номера',
            ], 400);
        }

        $catalogErrors = $this->catalogValidator($request);
        if ($catalogErrors !== null) {
            return response()->json(['success' => false, 'error' => $catalogErrors], 422);
        }

        $exist = UserCar::where('plate', $request->plate)->first();

        if ($exist) {
            return response()->json([
                'success' => false,
                'error' => 'Такой номер уже есть в базе',
                'error_code' => 'plate_exists',
            ], 400);
        }

        $modelId = $this->localModelId($request);
        if ($modelId === null) {
            return response()->json([
                'success' => false,
                'error' => 'Модель не найдена',
            ], 422);
        }

        $car = UserCar::create([
            'user_id' => $user->id,
            'model_id' => $modelId,
            'plate' => $request->plate,
            'year' => $request->filled('year') ? (int) $request->input('year') : null,
        ]);

        return response()->json([
            'success' => true,
            'car' => $this->carPayload($car),
            'msg' => 'Машина добавлена',
        ], 200);

    }

    private function localModelId(Request $request): ?int
    {
        $brandName = mb_substr(trim((string) $request->input('brand_name', '')), 0, 64);
        $modelName = mb_substr(trim((string) $request->input('model_name', '')), 0, 64);

        if ($brandName !== '' && $modelName !== '') {
            $brand = CarBrand::query()->firstOrCreate(['name' => $brandName]);
            $model = CarModel::query()->firstOrCreate(
                ['brand_id' => $brand->id, 'name' => $modelName],
                ['type' => BodyType::query()->where('name', 'sedan')->value('name')
                    ?? BodyType::query()->orderBy('id')->value('name')
                    ?? 'sedan']
            );

            return (int) $model->id;
        }

        if (! $request->filled('model_id') || ! $request->filled('source')) {
            return null;
        }

        return app(CarCatalog::class)->resolveModelId(
            (string) $request->input('source'),
            (int) $request->input('model_id'),
            $request->filled('brand_id') ? (int) $request->input('brand_id') : null,
        );
    }

    /** model_id is only meaningful with its id space (source); year is optional. */
    private function catalogValidator(Request $request): ?string
    {
        $v = Validator::make($request->all(), [
            'source' => ['nullable', 'in:'.CarCatalog::LOCAL.','.CarCatalog::REMOTE],
            'model_id' => ['nullable', 'integer', 'min:1'],
            'brand_id' => ['nullable', 'integer', 'min:1', 'required_if:source,'.CarCatalog::REMOTE],
            'year' => ['nullable', 'integer', 'between:'.CarCatalog::MIN_YEAR.','.((int) now()->year + 1)],
        ]);
        if ($v->fails()) {
            return $v->errors()->first();
        }
        if ($request->filled('model_id') && ! $request->filled('source')) {
            return 'source is required with model_id';
        }

        if ($request->input('source') === CarCatalog::REMOTE && $request->filled('year')) {
            $years = app(CarCatalog::class)->modelYears(CarCatalog::REMOTE, (int) $request->input('brand_id'), (int) $request->input('model_id'));
            if ($years !== null && ! in_array((int) $request->input('year'), $years, true)) {
                return 'Year is not available for the selected model';
            }
        }

        return null;
    }

    public function years(Request $request)
    {
        $data = $request->validate([
            'source' => ['nullable', 'in:local,carapi'],
            'brand_id' => ['nullable', 'integer', 'min:1'],
            'model_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $years = app(CarCatalog::class)->modelYears($data['source'] ?? 'local', (int) ($data['brand_id'] ?? 0), (int) ($data['model_id'] ?? 0));

        return response()->json(['success' => true, 'years' => $years ?? CarCatalog::years(), 'source' => $years === null ? 'manual' : 'catalog']);
    }

    public function showMyCar(Request $request, int $carid)
    {
        $car = $request->user()->cars()->findOrFail($carid);

        return response()->json(['success' => true, 'car' => $this->carPayload($car)]);
    }

    private function carPayload(UserCar $car): array
    {
        $car->loadMissing('model.brand');

        return ['id' => $car->id, 'plate' => $car->plate, 'year' => $car->year,
            'model' => $car->model, 'brand' => $car->model->brand, 'image' => $car->model->image];
    }

    public function editMyCar(Request $request, $carid)
    {
        try {
            $user = auth()->user();

            $validator = Validator::make($request->all(), [
                'plate' => ['sometimes', 'required', 'string', 'max:9', function ($attribute, $value, $fail) {
                    if (! preg_match('/^[A-Z]{2}[0-9]{3}[A-Z]{2}$/', $value)) {
                        $fail('The '.$attribute.' must be in the format of 2 letters 3 numbers 2 letters.');
                    }
                }],
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
            }

            $catalogErrors = $this->catalogValidator($request);
            if ($catalogErrors !== null) {
                return response()->json(['success' => false, 'error' => $catalogErrors], 422);
            }

            $car = $user->cars()->where('id', $carid)->first();
            if (! $car) {
                return response()->json(['success' => false, 'error' => 'Машина не найдена'], 404);
            }

            if ($request->filled('year') && ! $request->filled('model_id') && ! $request->filled('model_name') && $car->model->source === CarCatalog::REMOTE) {
                $years = app(CarCatalog::class)->modelYears(CarCatalog::REMOTE, (int) $car->model->brand->external_brand_id, (int) $car->model->external_model_id);
                if ($years !== null && ! in_array((int) $request->year, $years, true)) {
                    return response()->json(['success' => false, 'error' => __('booking.year_invalid')], 422);
                }
            }

            // Only whitelisted fields; never mass-assign user_id etc.
            $data = $request->only(['plate']);
            if ($request->has('year')) {
                $data['year'] = $request->filled('year') ? (int) $request->input('year') : null;
            }
            if ($request->filled('model_id') || ($request->filled('brand_name') && $request->filled('model_name'))) {
                $modelId = $this->localModelId($request);
                if ($modelId === null) {
                    return response()->json(['success' => false, 'error' => 'Модель не найдена'], 422);
                }
                $data['model_id'] = $modelId;
            }
            $car->update($data);

            return response()->json([
                'success' => true,
                'msg' => 'Машина обновлена',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Ошибка обновления',
            ], 400);
        }
    }

    public function removeMyCar(Request $request, $carid)
    {
        try {
            $user = auth()->user();
            $deleted = $user->cars()->where('id', $carid)->delete();
            if ($deleted === 0) {
                return response()->json(['success' => false, 'error' => 'Машина не найдена'], 404);
            }

            return response()->json([
                'success' => true,
                'msg' => 'Машина удалена',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Ошибка удаления',
            ], 400);
        }
    }
}
