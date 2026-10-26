<?php

namespace App\Http\Controllers;

use App\Exports\PartnerFleetReportExport;
use App\Models\CarWash;
use App\Models\CorporateClient;
use App\Models\FleetCar;
use App\Services\CorporateWashes;
use App\Services\FleetCars;
use App\Services\WashReportQuery;
use Illuminate\Http\RedirectResponse;
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
        if (! $client || ! Hash::check($data['password'], (string) $client->password)) {
            return back()->withInput($request->only('username'))->withErrors([
                'username' => __('admin.partner_login_failed'),
            ]);
        }

        if ($client->ban) {
            return back()->withInput($request->only('username'))->withErrors([
                'username' => __('admin.partner_login_failed'),
            ]);
        }

        if ($client->password_must_change && $client->temp_password_expires_at && $client->temp_password_expires_at->isPast()) {
            return back()->withInput($request->only('username'))->withErrors([
                'username' => __('admin.temp_password_expired'),
            ]);
        }

        $request->session()->regenerate();
        $request->session()->put('corporate_client_id', $client->id);
        $request->session()->put('corporate_session_version', (int) $client->session_version);

        if ($client->password_must_change) {
            return redirect()->route('partner.password');
        }

        return redirect()->route('partner.home');
    }

    public function dashboard(Request $request)
    {
        $corporate = $this->client($request);
        $today = now()->toDateString();
        $from = now()->subDays(13)->startOfDay();
        $washes = WashReportQuery::forFleet([], (int) $corporate->id);

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
            'invited' => $corporate->cars()->where('status', FleetCar::STATUS_INVITED)->count(),
            'washes' => (clone $washes)->count(),
            'today' => (clone $washes)->whereDate('date', $today)->count(),
            'pending' => (clone $washes)->where('approved', 0)->count(),
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
            'approved' => 'nullable|integer|between:0,5',
            'car_wash_id' => 'nullable|integer|min:1',
            'plate' => 'nullable|string|max:32',
            'export' => 'nullable',
        ]);

        $plate = empty($filters['plate']) ? null : (string) $filters['plate'];

        if ($request->filled('export')) {
            return Excel::download(
                new PartnerFleetReportExport($filters, (int) $corporate->id, $plate),
                'fleet-'.now()->format('Y-m-d-His').'.xlsx'
            );
        }

        $appointments = WashReportQuery::forFleet($filters, (int) $corporate->id, $plate)->paginate(20)->withQueryString();

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
        $cars = $corporate->cars()->with('userCar.model')->orderByDesc('id')->paginate(20);

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
            // UX-30: current password is only needed to set a new password.
            'current_password' => ['required_with:password', 'nullable', 'string'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        if (! empty($data['password']) && ! Hash::check((string) $data['current_password'], (string) $corporate->password)) {
            return back()->withErrors(['current_password' => __('admin.partner_password_wrong')]);
        }

        $corporate->username = $data['username'];
        if (! empty($data['password'])) {
            $corporate->password = $data['password'];
            $corporate->password_must_change = false;
            $corporate->temp_password_expires_at = null;
            $corporate->session_version = (int) $corporate->session_version + 1;
            $request->session()->put('corporate_session_version', (int) $corporate->session_version);
        }
        $corporate->credentials_custom = true;
        $corporate->save();

        return redirect()->route('partner.account')->with('message', __('admin.partner_account_saved'));
    }

    public function password(Request $request)
    {
        return view('partner.password', ['corporate' => $this->client($request)]);
    }

    public function savePassword(Request $request)
    {
        $corporate = $this->client($request);
        // First-login change only; a normal change goes through /partner/account with the current password.
        if (! $corporate->password_must_change) {
            return redirect()->route('partner.account')->withErrors(['password' => __('admin.partner_password_wrong')]);
        }
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $corporate->password = $data['password'];
        $corporate->password_must_change = false;
        $corporate->temp_password_expires_at = null;
        $corporate->credentials_custom = true;
        $corporate->session_version = (int) $corporate->session_version + 1;
        $corporate->save();

        $request->session()->regenerate();
        $request->session()->put('corporate_client_id', $corporate->id);
        $request->session()->put('corporate_session_version', (int) $corporate->session_version);

        return redirect()->route('partner.home')->with('message', __('admin.password_changed'));
    }

    public function storeCar(Request $request)
    {
        $corporate = $this->client($request);
        $data = $this->carFields($request);
        if ($data instanceof RedirectResponse) {
            return $data;
        }

        $result = FleetCars::add($corporate, $data, 'manual');
        if ($result !== 'added') {
            return redirect()->back()->withInput()->withErrors(FleetCars::addError($result));
        }

        return redirect()->route('partner.fleet')->with('message', __('admin.fleet_car_added'));
    }

    public function deleteCar(Request $request, FleetCar $car)
    {
        $corporate = $this->client($request);
        abort_unless($car->corporate_client_id === $corporate->id, 404);
        CorporateWashes::deleteCar($car, 'company');

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

        return redirect()->route('partner.fleet')
            ->with('message', __('admin.fleet_imported', ['added' => $result['added'], 'skipped' => $result['skipped']]))
            ->with('fleet_import_errors', array_slice($result['errors'], 0, 50));
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
        $corporate->forceFill(['api_token' => CorporateClient::makeToken()])->save();

        return redirect()->route('partner.fleet')
            ->with('message', __('admin.corporate_token_reset'))
            ->with('corporate_api_token', $corporate->api_token);
    }

    /** The form's car: catalog brand and model ids, year and, when the year has several, the body type. */
    private function carFields(Request $request): array|RedirectResponse
    {
        $data = $request->validate([
            'plate' => ['required', 'string', 'max:20'],
            'phone' => ['required', 'string', 'max:32'],
            'brand_id' => ['required', 'integer', 'min:1'],
            'model_id' => ['required', 'integer', 'min:1'],
            'year' => ['required', 'integer'],
            'body_type' => ['nullable', 'string', 'max:32'],
        ]);

        if (! FleetCars::validPlate($data['plate'])) {
            return back()->withInput()->withErrors(['plate' => __('admin.fleet_plate_format')]);
        }

        return $data;
    }

    private function client(Request $request): CorporateClient
    {
        return $request->attributes->get('corporate');
    }
}
