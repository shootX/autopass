<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AppointmentMoveController extends Controller
{
    /** POST /dashboard/appointments/{appointment}/move {date, time}: same rules as the manager's move (slot free, not finished). */
    public function move(Request $request, Appointment $appointment)
    {
        $data = $request->validate([
            'date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'time' => 'required|date_format:H:i',
        ]);
        try {
            $appointment = app(BookingService::class)->update($appointment, $data);
        } catch (ValidationException $e) {
            return redirect()->route('admin.appointments')->with('error', collect($e->errors())->flatten()->first());
        }

        $date = Carbon::parse($appointment->date)->format('d.m.Y');
        $time = substr((string) $appointment->time, 0, 5);
        $appointment->user?->sendPush(
            ['en' => 'Appointment rescheduled', 'ru' => 'Запись перенесена', 'ka' => 'ჩანაწერის დრო შეიცვალა'],
            ['en' => "Your appointment is now $date, $time.", 'ru' => "Новое время записи: $date, $time.", 'ka' => "ჯავშნის ახალი დროა $date, $time."]
        );

        return redirect()->route('admin.appointments')->with('message', __('admin.appointment_moved', ['date' => $date, 'time' => $time]));
    }
}
