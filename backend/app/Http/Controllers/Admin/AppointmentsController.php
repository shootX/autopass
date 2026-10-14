<?php

namespace App\Http\Controllers\Admin;

use App\Exports\AppointmentsExport;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class AppointmentsController extends Controller
{
    public function index(Request $request)
    {
        if ($request->has('export')) {
            return Excel::download(
                new AppointmentsExport,
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

        app(BookingService::class)->create((int) $request->user_id, $request->only(['car_wash_id', 'date', 'time', 'car_id', 'services']));

        return redirect()->route('admin.appointments')->with('message', __('admin.appointment_created'));
    }

    public function change_status(Request $request, Appointment $appointment): JsonResponse
    {
        $request->validate([
            'approved' => 'required|integer|between:0,5',
        ]);

        app(BookingService::class)->update($appointment, ['status' => (int) $request->approved]);

        return response()->json([
            'success' => true,
        ]);
    }
}
