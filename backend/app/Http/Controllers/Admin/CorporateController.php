<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\CorporateClient;
use App\Models\FleetCar;
use App\Services\FleetCars;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;

class CorporateController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $clients = CorporateClient::query()
            ->withCount('cars')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'ilike', '%'.$q.'%')
                        ->orWhere('phone', 'ilike', '%'.$q.'%')
                        ->orWhere('identification_code', 'ilike', '%'.$q.'%')
                        ->orWhere('contact', 'ilike', '%'.$q.'%');
                });
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.corporate.index', compact('clients', 'q'));
    }

    public function addPage()
    {
        return view('admin.corporate.form', ['client' => null]);
    }

    public function store(Request $request)
    {
        $data = $this->validateClient($request);
        if (CorporateClient::query()->where('username', $data['identification_code'])->exists()) {
            return back()->withInput()->withErrors(['identification_code' => __('admin.identification_taken')]);
        }

        $data['username'] = $data['identification_code'];
        $data['password'] = Hash::make($data['identification_code']);
        $data['credentials_custom'] = false;
        $data['api_token'] = CorporateClient::makeToken();
        $client = CorporateClient::query()->create($data);

        return redirect()->route('admin.corporate.show', $client)->with('message', __('admin.corporate_created'));
    }

    public function editPage(CorporateClient $corporate)
    {
        return view('admin.corporate.form', ['client' => $corporate]);
    }

    public function save(Request $request, CorporateClient $corporate)
    {
        $corporate->fill($this->validateClient($request, $corporate));
        if (!$corporate->credentials_custom && $corporate->isDirty('identification_code')) {
            $taken = CorporateClient::query()
                ->where('username', $corporate->identification_code)
                ->where('id', '!=', $corporate->id)
                ->exists();
            if ($taken) {
                return back()->withInput()->withErrors(['identification_code' => __('admin.identification_taken')]);
            }
            $corporate->username = $corporate->identification_code;
            $corporate->password = Hash::make($corporate->identification_code);
        }
        $corporate->save();

        return redirect()->route('admin.corporate.show', $corporate)->with('message', __('admin.corporate_updated'));
    }

    public function delete(CorporateClient $corporate)
    {
        $corporate->delete();

        return redirect()->route('admin.corporate')->with('message', __('admin.corporate_deleted'));
    }

    public function show(CorporateClient $corporate)
    {
        $cars = $corporate->cars()->orderByDesc('id')->paginate(20);

        return view('admin.corporate.show', compact('corporate', 'cars'));
    }

    public function storeCar(Request $request, CorporateClient $corporate)
    {
        $data = $this->carFields($request);
        if ($data instanceof \Illuminate\Http\RedirectResponse) {
            return $data;
        }

        $result = FleetCars::add($corporate, $data['plate'], $data['brand'], $data['model'], 'manual');
        if ($result !== 'added') {
            return redirect()->back()->withInput()->withErrors([
                'plate' => $result === 'duplicate' ? __('admin.fleet_plate_taken') : __('admin.fleet_plate_format'),
            ]);
        }

        return redirect()->route('admin.corporate.show', $corporate)->with('message', __('admin.fleet_car_added'));
    }

    public function deleteCar(CorporateClient $corporate, FleetCar $car)
    {
        abort_unless($car->corporate_client_id === $corporate->id, 404);
        $car->delete();

        return redirect()->route('admin.corporate.show', $corporate)->with('message', __('admin.fleet_car_deleted'));
    }

    public function import(Request $request, CorporateClient $corporate)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
        ]);

        $sheet = IOFactory::load($request->file('file')->getRealPath())->getActiveSheet()->toArray();
        $result = FleetCars::importSheet($corporate, $sheet, 'excel');

        return redirect()->route('admin.corporate.show', $corporate)->with(
            'message',
            __('admin.fleet_imported', $result)
        );
    }

    public function template()
    {
        $csv = FleetCars::templateCsv();

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="fleet-template.csv"',
        ]);
    }

    public function token(CorporateClient $corporate)
    {
        $corporate->update(['api_token' => CorporateClient::makeToken()]);

        return redirect()->route('admin.corporate.show', $corporate)->with('message', __('admin.corporate_token_reset'));
    }

    private function carFields(Request $request): array|\Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'plate' => ['required', 'string', 'max:20'],
            'brand_id' => ['required', 'integer', 'exists:car_brands,id'],
            'model_id' => ['required', 'integer', Rule::exists('car_models', 'id')->where(fn ($query) => $query->where('brand_id', $request->integer('brand_id')))],
        ]);

        if (!FleetCars::validPlate($data['plate'])) {
            return back()->withInput()->withErrors(['plate' => __('admin.fleet_plate_format')]);
        }

        return [
            'plate' => $data['plate'],
            'brand' => CarBrand::query()->find($data['brand_id'])->name,
            'model' => CarModel::query()->find($data['model_id'])->name,
        ];
    }

    private function validateClient(Request $request, ?CorporateClient $current = null): array
    {
        $request->merge([
            'identification_code' => preg_replace('/\D/', '', (string) $request->input('identification_code')),
        ]);

        return $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:200'],
            'legal_form' => ['required', 'string', 'max:40'],
            'identification_code' => ['required', 'regex:/^\d{9,11}$/', Rule::unique('corporate_clients', 'identification_code')->ignore($current?->id)],
            'legal_address' => ['required', 'string', 'max:255'],
            'actual_address' => ['required', 'string', 'max:255'],
            'bank_name' => ['required', 'string', 'max:120'],
            'bank_code' => ['required', 'string', 'max:20'],
            'bank_account' => ['required', 'string', 'max:40'],
            'contact' => ['required', 'string', 'max:160'],
            'phone' => ['required', 'string', 'max:32'],
            'email' => ['required', 'email', 'max:160', Rule::unique('corporate_clients', 'email')->ignore($current?->id)],
            'website' => ['nullable', 'url', 'max:200'],
            'vat_payer' => ['required', 'boolean'],
        ]);
    }
}
