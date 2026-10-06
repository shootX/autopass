<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BodyType;
use App\Models\CarBrand;
use App\Models\CarModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CarBrandsController extends Controller
{
    public function index(Request $request)
    {
        $brands = CarBrand::paginate(10);
        return view('admin.brands.index', compact('brands'));
    }

    public function addPage(Request $request)
    {
        return view('admin.brands.add');
    }

    public function storeCarBrand(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:car_brands,name',
        ]);

        if( $validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        CarBrand::create([
            'name' => $request->name,
        ]);

        return redirect()->route('admin.car_brands')->with('message', __('admin.brand_added'));
    }

    public function deleteCarBrand(Request $request, CarBrand $brand)
    {
        $brand->delete();
        return redirect()->route('admin.car_brands')->with('message', __('admin.brand_deleted'));
    }

    public function modelsIndex(Request $request, CarBrand $brand)
    {
        $models = $brand->models()->paginate(10);
        return view('admin.brands.models.index', compact('brand', 'models'));
    }

    public function modelsList(CarBrand $brand)
    {
        $models = $brand->models()->orderBy('name')->get(['id', 'name', 'type']);

        return response()->json([
            'brand' => [
                'id' => $brand->id,
                'name' => $brand->name,
            ],
            'models' => $models,
        ]);
    }

    public function modelsAddPage(Request $request, CarBrand $brand)
    {
        return view('admin.brands.models.add', compact('brand'));
    }

    public function modelStore(Request $request, CarBrand $brand)
    {
        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('car_models', 'name')->where(fn ($query) => $query->where('brand_id', $brand->id)),
            ],
            'type' => 'required|string|exists:body_types,name',
        ]);

        if( $validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $brand->models()->create([
            'name' => $request->name,
            'type' => $request->type,
        ]);

        return redirect()->route('admin.car_brand_models', $brand->id)->with('message', __('admin.model_added'));
    }
    public function modelDelete(Request $request, CarBrand $brand, CarModel $model)
    {
        if ((int) $model->brand_id !== (int) $brand->id) {
            abort(404);
        }

        $model->delete();
        return redirect()->route('admin.car_brand_models', $brand->id)->with('message', __('admin.model_deleted'));
    }
}
