<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CorporateClient;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Every car in the system on one page: drivers' own cars (user_cars), company cars a driver has activated
 * (a user_car linked from fleet_cars) and company cars still waiting for their driver (fleet_cars only).
 */
class CarsController extends Controller
{
    public const KINDS = ['personal', 'fleet'];

    public function index(Request $request)
    {
        $data = $request->validate([
            'search' => 'nullable|string|max:100',
            'kind' => 'nullable|in:'.implode(',', self::KINDS),
            'company' => 'nullable|integer',
        ]);

        $cars = DB::query()->fromSub($this->userCars()->unionAll($this->waitingFleetCars()), 'cars');

        if ($kind = $data['kind'] ?? null) {
            $cars->where('kind', $kind);
        }
        if ($company = $data['company'] ?? null) {
            $cars->where('company_id', $company);
        }
        if ($search = trim((string) ($data['search'] ?? ''))) {
            $like = '%'.mb_strtolower($search).'%';
            $plate = '%'.strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $search)).'%';
            $cars->where(function (Builder $query) use ($like, $plate) {
                if ($plate !== '%%') {
                    $query->where('plate', 'like', $plate)->orWhere('vin', 'like', $plate);
                }
                foreach (['brand', 'model', 'owner_name', 'owner_surname', 'owner_phone', 'company_name'] as $column) {
                    $query->orWhereRaw("LOWER({$column}) LIKE ?", [$like]);
                }
            });
        }

        $cars = $cars->orderByDesc('created_at')->orderByDesc('car_id')->paginate(25)->withQueryString();
        $companies = CorporateClient::query()->orderBy('name')->get(['id', 'name']);

        return view('admin.cars.index', compact('cars', 'companies'));
    }

    /** Drivers' cars, with the company when a fleet car points at one. */
    private function userCars(): Builder
    {
        return DB::table('user_cars as uc')
            ->leftJoin('car_models as cm', 'cm.id', '=', 'uc.model_id')
            ->leftJoin('car_brands as cb', 'cb.id', '=', 'cm.brand_id')
            ->leftJoin('users as u', 'u.id', '=', 'uc.user_id')
            ->leftJoin('fleet_cars as fc', 'fc.user_car_id', '=', 'uc.id')
            ->leftJoin('corporate_clients as cc', 'cc.id', '=', 'fc.corporate_client_id')
            ->selectRaw("CASE WHEN fc.id IS NULL THEN 'personal' ELSE 'fleet' END as kind")
            ->selectRaw('uc.id as car_id, uc.plate as plate, cb.name as brand, cm.name as model, uc.year as year')
            ->selectRaw('uc.vin as vin, uc.color as color, u.id as owner_id, u.name as owner_name, u.surname as owner_surname')
            ->selectRaw('u.phone as owner_phone, cc.id as company_id, cc.name as company_name, fc.status as fleet_status')
            ->selectRaw('uc.created_at as created_at');
    }

    /** Company cars no driver has activated yet: the driver's phone stands in for the owner. */
    private function waitingFleetCars(): Builder
    {
        return DB::table('fleet_cars as fc')
            ->join('corporate_clients as cc', 'cc.id', '=', 'fc.corporate_client_id')
            ->leftJoin('users as u', 'u.id', '=', 'fc.user_id')
            ->whereNull('fc.user_car_id')
            ->selectRaw("'fleet' as kind")
            ->selectRaw('fc.id as car_id, fc.plate as plate, fc.brand as brand, fc.model as model, fc.year as year')
            ->selectRaw('NULL as vin, NULL as color, u.id as owner_id, u.name as owner_name, u.surname as owner_surname')
            ->selectRaw('COALESCE(u.phone, fc.driver_phone) as owner_phone, cc.id as company_id, cc.name as company_name')
            ->selectRaw('fc.status as fleet_status, fc.created_at as created_at');
    }
}
