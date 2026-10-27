<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BodyType;
use App\Models\Package;
use App\Models\PackagePrice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PackagesController extends Controller
{
    /** Washes package prices: form field => months. */
    private const WASH_PRICES = ['price1' => 1, 'price2' => 3, 'price3' => 6, 'price4' => 12];

    public function index(Request $request)
    {
        $packages = Package::with('prices')->paginate(10);

        return view('admin.packages.index', compact('packages'));
    }

    public function create(Request $request)
    {
        $carTypes = BodyType::query()->ordered()->pluck('name');

        return view('admin.packages.add', compact('carTypes'));
    }

    public function store(Request $request)
    {
        $validator = $this->validator($request);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $this->save(new Package, $validator->validated());

        return redirect()->route('admin.packages.index')->with('message', __('admin.package_created'));
    }

    public function delete(Request $request, Package $package)
    {
        $package->delete();

        return redirect()->route('admin.packages.index')->with('message', __('admin.package_deleted'));
    }

    public function edit(Request $request, Package $package)
    {
        $carTypes = BodyType::query()->ordered()->pluck('name');
        if ($package->car_type && ! $carTypes->contains($package->car_type)) {
            $carTypes->prepend($package->car_type);
        }

        return view('admin.packages.edit', compact('package', 'carTypes'));
    }

    public function edit_save(Request $request, Package $package)
    {
        $validator = $this->validator($request);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $this->save($package, $validator->validated());

        return redirect()->route('admin.packages.index')->with('message', __('admin.package_updated'));
    }

    /** A washes package needs its four prices; a discount package its full price, percent and 30-day price. */
    private function validator(Request $request): \Illuminate\Validation\Validator
    {
        $discount = $request->input('type') === Package::TYPE_DISCOUNT;

        return Validator::make($request->all(), [
            'type' => ['required', Rule::in([Package::TYPE_WASHES, Package::TYPE_DISCOUNT])],
            'car_type' => 'required|string|max:64|exists:body_types,name',
            // A discount package's 0 is unlimited.
            'count_washes' => 'required|integer|min:0',
        ] + ($discount ? [
            'full_price' => 'required|numeric|min:0.01',
            'percent' => 'required|integer|min:1|max:100',
            'price_discount' => 'required|numeric|min:0',
        ] : array_fill_keys(array_keys(self::WASH_PRICES), 'required|numeric|min:0')));
    }

    private function save(Package $package, array $data): void
    {
        $discount = $data['type'] === Package::TYPE_DISCOUNT;

        DB::transaction(function () use ($package, $data, $discount) {
            $package->fill([
                'type' => $data['type'],
                'car_type' => $data['car_type'],
                'count_washes' => $data['count_washes'],
                'full_price' => $discount ? $data['full_price'] : null,
                'percent' => $discount ? $data['percent'] : null,
            ])->save();

            $package->prices()->delete();
            $prices = $discount ? [1 => $data['price_discount']]
                : collect(self::WASH_PRICES)->mapWithKeys(fn ($month, $field) => [$month => $data[$field]])->all();
            foreach ($prices as $month => $price) {
                PackagePrice::create(['package_id' => $package->id, 'month' => $month, 'price' => $price]);
            }
        });
    }
}
