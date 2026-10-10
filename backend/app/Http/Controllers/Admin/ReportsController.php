<?php

namespace App\Http\Controllers\Admin;

use App\Exports\WashReportExport;
use App\Http\Controllers\Controller;
use App\Models\BodyType;
use App\Models\CarBrand;
use App\Models\CarWash;
use App\Models\CarWashService;
use App\Models\Package;
use App\Models\User;
use App\Services\WashReportQuery;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportsController extends Controller
{
    public function washes(Request $request)
    {
        $filters = $request->validate([
            'id' => 'nullable|integer|min:1',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'time_from' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'time_to' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'created_from' => 'nullable|date',
            'created_to' => 'nullable|date',
            'approved' => 'nullable|in:0,1',
            'car_wash_id' => 'nullable|integer|min:1',
            'manager_id' => 'nullable|integer|min:1',
            'service_id' => 'nullable|integer|min:1',
            'brand_id' => 'nullable|integer|min:1',
            'count_washes' => 'nullable|integer|min:1',
            'qr_code' => 'nullable|string|max:12',
            'client' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'plate' => 'nullable|string|max:16',
            'car_type' => 'nullable|string|max:64',
            'package_type' => 'nullable|string|max:64',
            'export' => 'nullable',
        ]);

        if ($request->filled('export')) {
            return Excel::download(
                new WashReportExport($filters),
                'washes-'.now()->format('Y-m-d-His').'.xlsx'
            );
        }

        $appointments = WashReportQuery::make($filters)
            ->paginate(20)
            ->withQueryString();

        return view('admin.reports.washes', [
            'appointments' => $appointments,
            'washes' => CarWash::query()->orderBy('name')->get(['id', 'name', 'address']),
            'managers' => User::query()->where('role', User::ROLE_MANAGER)->orderBy('name')->get(['id', 'name', 'surname']),
            'services' => CarWashService::query()->orderBy('name')->get(['id', 'name']),
            'brands' => CarBrand::query()->orderBy('name')->get(['id', 'name']),
            'carTypes' => BodyType::query()->orderBy('name')->pluck('name'),
            'packageTypes' => Package::query()->distinct()->orderBy('car_type')->pluck('car_type'),
            'washCounts' => Package::query()->distinct()->orderBy('count_washes')->pluck('count_washes'),
        ]);
    }
}
