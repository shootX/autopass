<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\BodyType;
use App\Models\CarWash;
use App\Models\User;
use App\Services\WashReportQuery;
use App\Support\Phone;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Mockery\Exception;

class StatisticsController extends Controller
{
    public function my_washes(Request $request)
    {
        //Current user
        $user = auth()->user();
        //Check if manager
        if ($user->role != User::ROLE_MANAGER) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden'
            ], 403);
        }

        $myWashes = CarWash::where('manager_id', $user->id)->first();

        if($myWashes)
        {
            return response()->json([
                'success' => true,
                'my_wash' => $myWashes
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Car wash not found'
            ], 404);
        }
    }
    public function report(Request $request)
    {
        $user = auth()->user();
        if ((int) $user->role !== User::ROLE_MANAGER) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $wash = CarWash::query()->where('manager_id', $user->id)->first();
        if (! $wash) {
            return response()->json(['success' => false, 'message' => 'Car wash not found'], 404);
        }

        $filters = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'time_from' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'time_to' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'car_type' => 'nullable|string|max:64',
        ]);
        $filters['car_wash_id'] = $wash->id;

        $query = WashReportQuery::make($filters);
        $total = (clone $query)->count();
        $appointments = $query->limit(300)->get();

        return response()->json([
            'success' => true,
            'wash' => [
                'id' => $wash->id,
                'name' => $wash->name,
            ],
            'total' => $total,
            'body_types' => BodyType::query()->orderBy('name')->pluck('name')->values(),
            'rows' => $appointments->map(function (Appointment $appointment) {
                $car = $appointment->car;
                $model = $car?->model;

                return [
                    'id' => $appointment->id,
                    'date' => Carbon::parse($appointment->date)->format('d.m.Y'),
                    'time' => Carbon::parse($appointment->time)->format('H:i'),
                    'status' => $this->statusCode($appointment->approved),
                    'client' => $appointment->user?->displayName(),
                    'phone' => Phone::format($appointment->user?->phone),
                    'plate' => $car?->plate,
                    'car' => trim(($model?->brand?->name ?? '').' '.($model?->name ?? '')),
                    'body_type' => $model?->type,
                    'services' => $appointment->servicesList,
                ];
            })->values(),
        ]);
    }

    public function statistic(Request $request, CarWash $carWash)
    {
        try {
            //Current user
            $user = auth()->user();
            //Check if manager
            if ($user->role != User::ROLE_MANAGER) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden'
                ], 403);
            }
            //Check if manager is owner of car wash
            if ($carWash->manager_id != $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden'
                ], 403);
            }
            //Validation
            $validator = Validator::make($request->all(), [
                'period' => 'required|in:today,yesterday,week,month'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid period'
                ], 400);
            }

            //Get statistics
            $period = $request->input('period');
            $end = now();
            switch ($period) {
                case 'today':
                    $start = now()->startOfDay();
                    break;
                case 'yesterday':
                    $start = now()->startOfDay()->subDay();
                    $end = now()->endOfDay()->subDay();
                    break;
                case 'week':
                    $start = now()->startOfWeek();
                    break;
                case 'month':
                    $start = now()->startOfMonth();
                    break;
            }
            //Get statistics
            $washCount = Appointment::where('car_wash_id', $carWash->id)
                ->whereBetween('date', [$start, $end])
                ->where('approved', true)
                ->count();

            $car_types = Appointment::query()
                ->join('user_packages', 'user_packages.user_car_id', '=', 'appointments.car_id')
                ->join('packages', 'packages.id', '=', 'user_packages.package_id')
                ->where('appointments.car_wash_id', $carWash->id)
                ->whereBetween('appointments.date', [$start, $end])
                ->where('appointments.approved', true)
                ->select('packages.car_type', DB::raw('COUNT(*) as count'))
                ->groupBy('packages.car_type')
                ->pluck('count', 'packages.car_type');

            $packageStats = Appointment::query()
                ->join('user_packages', 'user_packages.user_car_id', '=', 'appointments.car_id')
                ->join('packages', 'packages.id', '=', 'user_packages.package_id')
                ->where('appointments.car_wash_id', $carWash->id)
                ->whereBetween('appointments.date', [$start, $end])
                ->where('appointments.approved', true)
                ->select(
                    'packages.count_washes',
                    DB::raw('COUNT(DISTINCT appointments.user_id) as count')
                )
                ->groupBy('packages.count_washes')
                ->orderBy('packages.count_washes')
                ->pluck('count', 'packages.count_washes')
                ->toArray();

            $data = [
                'whashes' => $washCount,
                'packages' => $packageStats,
                'car_types' => $car_types,
                'graph' => null
            ];

            if(in_array($period, ['week', 'month']))
            {
                $washCountWeek = Appointment::where('car_wash_id', $carWash->id)
                    ->whereBetween('date', [$start, $end])
                    ->where('approved', true)
                    ->select(
                        'date as day',
                        DB::raw('COUNT(*) as count')
                    )
                    ->groupBy('date')
                    ->orderBy('date')
                    ->pluck('count', 'day')
                    ->toArray();

                $data['graph'] = $washCountWeek;
            }

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (Exception $e)
        {
            return response()->json([
                'success' => false,
                'message' => 'Some error'
            ], 500);
        }
    }

    private function statusCode(mixed $approved): string
    {
        if ($approved === true || $approved === 1 || $approved === '1' || $approved === 't') {
            return 'confirmed';
        }

        if ($approved === false || $approved === 0 || $approved === '0' || $approved === 'f' || $approved === null) {
            return 'pending';
        }

        return match ((string) $approved) {
            '2' => 'cancelled',
            '3' => 'completed',
            default => 'pending',
        };
    }
}
