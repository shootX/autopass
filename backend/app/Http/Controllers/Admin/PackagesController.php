<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BodyType;
use App\Models\Package;
use App\Models\PackagePrice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PackagesController extends Controller
{
    public function index(Request $request)
    {
        $packages = Package::with('prices')->paginate(10);
        return view('admin.packages.index', compact('packages'));
    }

    public function create(Request $request)
    {
        $carTypes = BodyType::query()->orderBy('name')->pluck('name');

        return view('admin.packages.add', compact('carTypes'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'car_type' => 'required|string|max:64|exists:body_types,name',
            'count_washes' => 'required|numeric|min:0',
            'price1' => 'required|numeric|min:0',
            'price2' => 'required|numeric|min:0',
            'price3' => 'required|numeric|min:0',
            'price4' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $package = Package::create([
            'car_type' => $request->car_type,
            'count_washes' => $request->count_washes,
        ]);

        PackagePrice::create([
            'package_id' => $package->id,
            'month' => 1,
            'price' => $request->price1,
        ]);

        PackagePrice::create([
            'package_id' => $package->id,
            'month' => 3,
            'price' => $request->price2,
        ]);

        PackagePrice::create([
            'package_id' => $package->id,
            'month' => 6,
            'price' => $request->price3,
        ]);

        PackagePrice::create([
            'package_id' => $package->id,
            'month' => 12,
            'price' => $request->price4,
        ]);

        return redirect()->route('admin.packages.index')->with('message', __('admin.package_created'));
    }

    public function delete(Request $request, Package $package)
    {
        $package->delete();
        return redirect()->route('admin.packages.index')->with('message', __('admin.package_deleted'));
    }

    public function edit(Request $request, Package $package)
    {
        $carTypes = BodyType::query()->orderBy('name')->pluck('name');
        if ($package->car_type && ! $carTypes->contains($package->car_type)) {
            $carTypes->prepend($package->car_type);
        }

        return view('admin.packages.edit', compact('package', 'carTypes'));
    }

    public function edit_save(Request $request, Package $package)
    {
        $validator = Validator::make($request->all(), [
            'car_type' => 'required|string|max:64|exists:body_types,name',
            'count_washes' => 'required|numeric|min:0',
            'price1' => 'required|numeric|min:0',
            'price2' => 'required|numeric|min:0',
            'price3' => 'required|numeric|min:0',
            'price4' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $package->update([
            'car_type' => $request->car_type,
            'count_washes' => $request->count_washes,
        ]);

        $package->prices()->delete();

        PackagePrice::create([
            'package_id' => $package->id,
            'month' => 1,
            'price' => $request->price1,
        ]);

        PackagePrice::create([
            'package_id' => $package->id,
            'month' => 3,
            'price' => $request->price2,
        ]);

        PackagePrice::create([
            'package_id' => $package->id,
            'month' => 6,
            'price' => $request->price3,
        ]);

        PackagePrice::create([
            'package_id' => $package->id,
            'month' => 12,
            'price' => $request->price4,
        ]);

        return redirect()->route('admin.packages.index')->with('message', __('admin.package_updated'));
    }
}
