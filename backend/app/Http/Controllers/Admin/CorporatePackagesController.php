<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BodyType;
use App\Models\CorporateClient;
use App\Models\CorporatePackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Packages menu, corporate tab: the admin makes a package for one company. A wash package is
 * credited when its invoice is paid; a discount package gives a price per body type. A company may
 * have several active discount packages and picks one for each discount car.
 */
class CorporatePackagesController extends Controller
{
    public function index(Request $request)
    {
        $company = $request->integer('company') ?: null;
        $packages = CorporatePackage::query()
            ->with(['client:id,name', 'prices'])
            ->when($company, fn ($query) => $query->where('corporate_client_id', $company))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();
        $companies = CorporateClient::query()->orderBy('name')->get(['id', 'name']);

        return view('admin.packages.corporate.index', compact('packages', 'companies', 'company'));
    }

    public function create(Request $request)
    {
        $package = new CorporatePackage(['is_active' => true]);
        $package->type = $request->query('type') === CorporatePackage::TYPE_DISCOUNT ? CorporatePackage::TYPE_DISCOUNT : CorporatePackage::TYPE_WASHES;
        $package->corporate_client_id = $request->integer('company') ?: null;

        return $this->form($package);
    }

    public function store(Request $request)
    {
        $base = $request->validate([
            'corporate_client_id' => ['required', 'integer', Rule::exists('corporate_clients', 'id')],
            'type' => ['required', 'string', Rule::in(CorporatePackage::TYPES)],
        ]);
        $package = new CorporatePackage;
        $package->forceFill($base);
        $this->persist($request, $package);

        return redirect()->route('admin.packages.corporate', ['company' => $package->corporate_client_id])
            ->with('message', __('admin.cp_saved'));
    }

    public function edit(CorporatePackage $corporatePackage)
    {
        return $this->form($corporatePackage->load('prices'));
    }

    /** The company and the type stay as created: the ledger and past washes point at this package. */
    public function save(Request $request, CorporatePackage $corporatePackage)
    {
        $this->persist($request, $corporatePackage);

        return redirect()->route('admin.packages.corporate', ['company' => $corporatePackage->corporate_client_id])
            ->with('message', __('admin.cp_saved'));
    }

    public function delete(CorporatePackage $corporatePackage)
    {
        // Ledger rows and past washes keep their numbers; they only lose the link to the package.
        $corporatePackage->delete();

        return redirect()->route('admin.packages.corporate', ['company' => $corporatePackage->corporate_client_id])
            ->with('message', __('admin.cp_deleted'));
    }

    private function form(CorporatePackage $package)
    {
        return view('admin.packages.corporate.form', [
            'package' => $package,
            'companies' => CorporateClient::query()->orderBy('name')->get(['id', 'name']),
            'bodyTypes' => BodyType::query()->ordered()->pluck('name'),
            'prices' => $package->exists ? $package->prices->keyBy('car_type') : collect(),
        ]);
    }

    private function persist(Request $request, CorporatePackage $package): void
    {
        $discount = $package->isDiscount();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'washes' => $discount ? ['nullable'] : ['required', 'integer', 'min:1', 'max:100000'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'monthly_limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'prices' => [$discount ? 'required' : 'nullable', 'array'],
            'prices.*.full_price' => ['nullable', 'numeric', 'min:0.01', 'max:99999', 'required_with:prices.*.percent'],
            'prices.*.percent' => ['nullable', 'integer', 'between:1,100', 'required_with:prices.*.full_price'],
        ]);
        $rows = $discount ? $this->priceRows($data['prices'] ?? []) : [];

        DB::transaction(function () use ($request, $package, $data, $discount, $rows) {
            $package->fill([
                'title' => $data['title'],
                'washes' => $discount ? 0 : (int) $data['washes'],
                'price' => $data['price'] ?? null,
                'monthly_limit' => $discount ? ($data['monthly_limit'] ?? null) : null,
                'is_active' => $request->boolean('is_active'),
            ])->save();

            if ($discount) {
                $package->prices()->delete();
                $package->prices()->createMany($rows);
            }
        });
    }

    /** The filled rows only; a body type left empty gets no discount. */
    private function priceRows(array $prices): array
    {
        $known = BodyType::query()->pluck('name')->all();
        $rows = [];
        foreach ($prices as $type => $row) {
            if (! is_array($row) || ($row['full_price'] ?? null) === null || ($row['percent'] ?? null) === null) {
                continue;
            }
            if (! in_array((string) $type, $known, true)) {
                throw ValidationException::withMessages(['prices' => __('admin.cp_prices_unknown')]);
            }
            $rows[] = ['car_type' => (string) $type, 'full_price' => $row['full_price'], 'percent' => (int) $row['percent']];
        }
        if ($rows === []) {
            throw ValidationException::withMessages(['prices' => __('admin.cp_prices_required')]);
        }

        return $rows;
    }
}
