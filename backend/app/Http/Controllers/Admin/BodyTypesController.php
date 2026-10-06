<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BodyType;
use App\Models\CarModel;
use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BodyTypesController extends Controller
{
    public function index()
    {
        $types = BodyType::query()->orderBy('name')->paginate(20);

        return view('admin.body-types.index', compact('types'));
    }

    public function addPage()
    {
        return view('admin.body-types.add');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:64|unique:body_types,name',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        BodyType::create([
            'name' => $request->name,
            'image' => $this->storeImage($request),
        ]);
        BodyType::forgetImages();

        return redirect()->route('admin.body_types')->with('message', __('admin.body_type_added'));
    }

    public function edit(BodyType $bodyType)
    {
        return view('admin.body-types.edit', compact('bodyType'));
    }

    public function save(Request $request, BodyType $bodyType)
    {
        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:64',
                Rule::unique('body_types', 'name')->ignore($bodyType->id),
            ],
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $oldName = $bodyType->name;
        $data = ['name' => $request->name];
        if ($image = $this->storeImage($request)) {
            $data['image'] = $image;
        }

        DB::transaction(function () use ($bodyType, $oldName, $data) {
            if ($oldName !== $data['name']) {
                CarModel::query()->where('type', $oldName)->update(['type' => $data['name']]);
                Package::query()->where('car_type', $oldName)->update(['car_type' => $data['name']]);
            }
            $bodyType->update($data);
        });
        BodyType::forgetImages();

        return redirect()->route('admin.body_types')->with('message', __('admin.body_type_updated'));
    }

    public function delete(BodyType $bodyType)
    {
        $used = CarModel::query()->where('type', $bodyType->name)->exists()
            || Package::query()->where('car_type', $bodyType->name)->exists();

        if ($used) {
            return redirect()->route('admin.body_types')->with('error', __('admin.body_type_in_use'));
        }

        $bodyType->delete();
        BodyType::forgetImages();

        return redirect()->route('admin.body_types')->with('message', __('admin.body_type_deleted'));
    }

    private function storeImage(Request $request): ?string
    {
        if (! $request->hasFile('image')) {
            return null;
        }

        return '/storage/'.$request->file('image')->store('body-types', 'public');
    }
}
