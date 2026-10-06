<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BodyType;
use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\UserCar;
use App\Services\CarCatalog;
use Auth;
use Illuminate\Support\Facades\Validator;

class CarsController extends Controller
{
    public function brands(Request $request)
    {
        try {
            $brands = array_map(fn (array $brand) => [
                'id' => $brand['id'],
                'name' => $brand['name'],
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
                'brand' => ['id' => (int) $brand_id, 'name' => $brand['name'] ?? ''],
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
                    'model' => $m,
                    'brand' => $b,
                    'image' => $m->image,
                ];
            });

            return response()->json([
                'success' => true,
                'cars' => $cars
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function addMyCar(Request $request)
    {
        $user = auth()->user();

        $validator = Validator::make($request->all(), [
            'plate' => ['required', 'string', 'max:9', function ($attribute, $value, $fail) {
                if (!preg_match('/^[A-Z]{2}[0-9]{3}[A-Z]{2}$/', $value)) {
                    $fail('The '.$attribute.' must be in the format of 2 letters 3 numbers 2 letters.');
                }
            }],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Не верный формат номера'
            ], 400);
        }

        $exist = UserCar::where('plate', $request->plate)->first();

        if($exist)
        {
            return response()->json([
                'success' => false,
                'error' => 'Такой номер уже есть в базе'
            ], 400);
        }

        $modelId = $this->localModelId($request);
        if ($modelId === null) {
            return response()->json([
                'success' => false,
                'error' => 'Модель не найдена',
            ], 400);
        }

        UserCar::create([
            'user_id' => $user->id,
            'model_id' => $modelId,
            'plate' => $request->plate,
        ]);

        return response()->json([
            'success' => true,
            'msg' => 'Машина добавлена'
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

        $id = (int) $request->input('model_id');

        return CarModel::query()->whereKey($id)->exists() ? $id : null;
    }

    public function editMyCar(Request $request, $carid)
    {
        try {
            $user = auth()->user();

            $validator = Validator::make($request->all(), [
                'plate' => ['sometimes', 'required', 'string', 'max:9', function ($attribute, $value, $fail) {
                    if (!preg_match('/^[A-Z]{2}[0-9]{3}[A-Z]{2}$/', $value)) {
                        $fail('The '.$attribute.' must be in the format of 2 letters 3 numbers 2 letters.');
                    }
                }],
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
            }

            $user->cars()->where('id', $carid)->update($request->all());

            return response()->json([
                'success' => true,
                'msg' => 'Машина обновлена'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Ошибка обновления'
            ], 400);
        }
    }

    public function removeMyCar(Request $request, $carid)
    {
        try {
            $user = auth()->user();
            $user->cars()->where('id', $carid)->delete();

            return response()->json([
                'success' => true,
                'msg' => 'Машина удалена'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Ошибка удаления'
            ], 400);
        }
    }
}
