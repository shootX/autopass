<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BodyType;
use App\Models\CarModel;
use App\Models\CorporatePackagePrice;
use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BodyTypesController extends Controller
{
    public function index()
    {
        $types = BodyType::query()->ordered()->paginate(20);

        return view('admin.body-types.index', compact('types'));
    }

    public function addPage()
    {
        return view('admin.body-types.add');
    }

    public function store(Request $request)
    {
        $request->merge(['name' => Str::slug((string) $request->input('name_en'), '_')]);
        $validator = Validator::make($request->all(), $this->rules() + [
            'name' => 'required|string|max:64|unique:body_types,name',
        ], [], ['name' => __('admin.body_type_name_en')]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        BodyType::create($request->only('name', 'name_ka', 'name_en') + [
            'sort' => (int) BodyType::query()->max('sort') + 1,
        ]);
        BodyType::forgetLabels();

        return redirect()->route('admin.body_types')->with('message', __('admin.body_type_added'));
    }

    public function edit(BodyType $bodyType)
    {
        return view('admin.body-types.edit', compact('bodyType'));
    }

    /** Only the names change: the key stays, since packages, models and prices store it. */
    public function save(Request $request, BodyType $bodyType)
    {
        $validator = Validator::make($request->all(), $this->rules($bodyType));

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $bodyType->update($request->only('name_ka', 'name_en'));
        BodyType::forgetLabels();

        return redirect()->route('admin.body_types')->with('message', __('admin.body_type_updated'));
    }

    public function delete(BodyType $bodyType)
    {
        $used = CarModel::query()->where('type', $bodyType->name)->exists()
            || Package::query()->where('car_type', $bodyType->name)->exists()
            || CorporatePackagePrice::query()->where('car_type', $bodyType->name)->exists();

        if ($used) {
            return redirect()->route('admin.body_types')->with('error', __('admin.body_type_in_use'));
        }

        $bodyType->delete();
        BodyType::forgetLabels();

        return redirect()->route('admin.body_types')->with('message', __('admin.body_type_deleted'));
    }

    private function rules(?BodyType $ignore = null): array
    {
        return [
            'name_ka' => ['required', 'string', 'max:64', Rule::unique('body_types', 'name_ka')->ignore($ignore?->id)],
            'name_en' => ['required', 'string', 'max:64', Rule::unique('body_types', 'name_en')->ignore($ignore?->id)],
        ];
    }
}
