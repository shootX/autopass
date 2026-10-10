<?php

namespace App\Http\Controllers;

use App\Exports\PartnerFleetReportExport;
use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\CarWash;
use App\Models\CorporateClient;
use App\Models\FleetCar;
use App\Services\FleetCars;
use App\Services\WashReportQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PartnerPanelController extends Controller
{
    public function loginPage(Request $request)
    {
        if ($request->session()->has('corporate_client_id')) {
            return redirect()->route('partner.home');
        }

        return view('partner.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $client = CorporateClient::query()->where('username', $data['username'])->first();
        if (!$client || !Hash::check($data['password'], (string) $client->password)) {
            return back()->withInput($request->only('username'))->withErrors([
                'username' => __('admin.partner_login_failed'),
            ]);
        }

        $request->session()->regenerate();
        $request->session()->put('corporate_client_id', $client->id);

        return redirect()->route('partner.home');
    }

    public function dashboard(Request $request)
    {
        $corporate = $this->client($request);
        $plates = $corporate->cars()->pluck('plate')->all();
        $today = now()->toDateString();
        $from = now()->subDays(13)->startOfDay();
        $washes = $this->washes($plates);

        $dayCounts = (clone $washes)
            ->reorder()
            ->whereBetween('date', [$from->toDateString(), $today])
            ->selectRaw('date as day, COUNT(*) as total')
            ->groupBy('date')
            ->pluck('total', 'day');

        $trend = collect(range(0, 13))->map(function (int $offset) use ($from, $dayCounts) {
            $day = $from->copy()->addDays($offset);

            return [
                'label' => $day->format('d.m'),
                'total' => (int) ($dayCounts[$day->toDateString()] ?? 0),
            ];
        });

        $byWash = (clone $washes)
            ->reorder()
            ->join('car_washes', 'car_washes.id', '=', 'appointments.car_wash_id')
            ->selectRaw('car_washes.name as label, COUNT(*) as total')
            ->groupBy('car_washes.id', 'car_washes.name')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        $byPlate = (clone $washes)
            ->reorder()
            ->join('user_cars', 'user_cars.id', '=', 'appointments.car_id')
            ->selectRaw('user_cars.plate as label, COUNT(*) as total')
            ->groupBy('user_cars.plate')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        $recent = (clone $washes)->with(['car.model.brand', 'washing'])->limit(8)->get();

        $stats = [
            'cars' => $corporate->cars()->count(),
            'washes' => (clone $washes)->count(),
            'today' => (clone $washes)->whereDate('date', $today)->count(),
            'pending' => (clone $washes)->where('approved', false)->count(),
        ];
        $maxTrend = max(1, $trend->max('total'));

        return view('partner.dashboard', compact('stats', 'trend', 'maxTrend', 'byWash', 'byPlate', 'recent'));
    }

    public function reports(Request $request)
    {
        $corporate = $this->client($request);
        $filters = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'approved' => 'nullable|in:0,1',
            'car_wash_id' => 'nullable|integer|min:1',
            'plate' => 'nullable|string|max:32',
            'export' => 'nullable',
        ]);

        $plates = $corporate->cars()->pluck('plate')->all();
        if (!empty($filters['plate'])) {
            $asked = FleetCars::plate($filters['plate']);
            $own = array_map(fn ($plate) => FleetCars::plate((string) $plate), $plates);
            if (!in_array($asked, $own, true)) {
                $plates = [];
            }
        }

        if ($request->filled('export')) {
            return Excel::download(
                new PartnerFleetReportExport($filters, $plates),
                'fleet-'.now()->format('Y-m-d-His').'.xlsx'
            );
        }

        $appointments = WashReportQuery::forPlates($filters, $plates)->paginate(20)->withQueryString();

        return view('partner.reports', [
            'appointments' => $appointments,
            'cars' => $corporate->cars()->orderBy('plate')->get(['plate', 'brand', 'model']),
            'washes' => CarWash::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function logout(Request $request)
    {
        $request->session()->forget('corporate_client_id');
        $request->session()->regenerate();

        return redirect()->route('partner.login');
    }

    public function home(Request $request)
    {
        $corporate = $this->client($request);
        $cars = $corporate->cars()->orderByDesc('id')->paginate(20);

        return view('partner.home', compact('corporate', 'cars'));
    }

    public function account(Request $request)
    {
        return view('partner.account', ['corporate' => $this->client($request)]);
    }

    public function saveAccount(Request $request)
    {
        $corporate = $this->client($request);
        $data = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:64', Rule::unique('corporate_clients', 'username')->ignore($corporate->id)],
            'current_password' => ['required', 'string'],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
        ]);

        if (!Hash::check($data['current_password'], (string) $corporate->password)) {
            return back()->withErrors(['current_password' => __('admin.partner_password_wrong')]);
        }

        $corporate->username = $data['username'];
        if (!empty($data['password'])) {
            $corporate->password = Hash::make($data['password']);
        }
        $corporate->credentials_custom = true;
        $corporate->save();

        return redirect()->route('partner.account')->with('message', __('admin.partner_account_saved'));
    }

    public function storeCar(Request $request)
    {
        $corporate = $this->client($request);
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

        return redirect()->route('partner.fleet')->with('message', __('admin.fleet_car_added'));
    }

    public function deleteCar(Request $request, FleetCar $car)
    {
        $corporate = $this->client($request);
        abort_unless($car->corporate_client_id === $corporate->id, 404);
        $car->delete();

        return redirect()->route('partner.fleet')->with('message', __('admin.fleet_car_deleted'));
    }

    public function import(Request $request)
    {
        $corporate = $this->client($request);
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
        ]);

        $sheet = IOFactory::load($request->file('file')->getRealPath())->getActiveSheet()->toArray();
        $result = FleetCars::importSheet($corporate, $sheet, 'excel');

        return redirect()->route('partner.fleet')->with('message', __('admin.fleet_imported', $result));
    }

    public function template()
    {
        $csv = FleetCars::templateCsv();

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="fleet-template.csv"',
        ]);
    }

    public function token(Request $request)
    {
        $corporate = $this->client($request);
        $corporate->update(['api_token' => CorporateClient::makeToken()]);

        return redirect()->route('partner.fleet')->with('message', __('admin.corporate_token_reset'));
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

    private function client(Request $request): CorporateClient
    {
        return $request->attributes->get('corporate');
    }

    private function washes(array $plates)
    {
        return WashReportQuery::forPlates([], $plates);
    }
}
