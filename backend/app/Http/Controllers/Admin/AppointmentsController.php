<?php

namespace App\Http\Controllers\Admin;

use App\Exports\AppointmentsExport;
use App\Exports\PartnersExport;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\CarWash;
use http\Env\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class AppointmentsController extends Controller
{
    public function index(Request $request)
    {
        if($request->has('export'))
        {
            return Excel::download(
                new AppointmentsExport(),
                'appointments.xlsx'
            );
        }
        $appointments = Appointment::orderBy('id', 'desc')->paginate(10);
        return view('admin.appointments.index', compact('appointments'));
    }

    public function remove(Request $request, Appointment $appointment)
    {
        $appointment->delete();
        return redirect()->back()->with('message', __('admin.appointment_deleted'));
    }

    public function addPage(Request $request)
    {
        return view('admin.appointments.add');
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required',
            'car_wash_id' => 'required',
            'date' => 'required',
            'time' => 'required',
            'car_id' => 'required',
            'services' => 'required|array',
        ]);

        $appointment = Appointment::create([
            'user_id' => $request->user_id,
            'car_wash_id' => $request->car_wash_id,
            'date' => $request->date,
            'time' => $request->time,
            'car_id' => $request->car_id,
            'qr_code' => Str::random(12),
        ]);

        foreach ($request->services as $service)
        {
            $appointment->services()->attach($service);
        }

        return redirect()->route('admin.appointments')->with('message', __('admin.appointment_created'));
    }

    public function change_status(Request $request, Appointment $appointment) : JsonResponse
    {
        $request->validate([
            'approved' => 'required|in:0,1,2,3',
        ]);

        $appointment->approved = $request->approved;
        $appointment->update();

        return response()->json([
            'success' => true
        ]);
    }
}
