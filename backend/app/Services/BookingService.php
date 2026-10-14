<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentsService;
use App\Models\CarWash;
use App\Models\UserCar;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function create(int $userId, array $data): Appointment
    {
        return DB::transaction(function () use ($userId, $data) {
            $wash = CarWash::query()->lockForUpdate()->findOrFail($data['car_wash_id']);
            $car = UserCar::query()->where('user_id', $userId)->findOrFail($data['car_id']);
            $services = array_values(array_unique($data['services']));
            if ($wash->services()->whereIn('car_wash_services.id', $services)->count() !== count($services)) {
                throw ValidationException::withMessages(['service_id' => __('booking.service_unavailable')]);
            }
            $this->validateSlot($wash, $data['date'], $data['time']);
            $appointment = Appointment::create([
                'user_id' => $userId, 'car_wash_id' => $wash->id, 'car_id' => $car->id,
                'date' => $data['date'], 'time' => $data['time'], 'approved' => AppointmentStatus::Pending->value,
                'qr_code' => Str::random(12),
            ]);
            foreach ($services as $service) {
                AppointmentsService::create(['appointment_id' => $appointment->id, 'car_wash_service_id' => $service]);
            }

            return $appointment;
        });
    }

    public function update(Appointment $appointment, array $data): Appointment
    {
        return DB::transaction(function () use ($appointment, $data) {
            $wash = CarWash::query()->lockForUpdate()->findOrFail($appointment->car_wash_id);
            $locked = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            $before = AppointmentStatus::from((int) $locked->approved);
            $date = $data['date'] ?? $locked->date;
            $time = $data['time'] ?? substr($locked->time, 0, 5);
            $moved = $date !== $locked->date || substr($time, 0, 5) !== substr($locked->time, 0, 5);
            $status = isset($data['status']) ? AppointmentStatus::from((int) $data['status']) : $before;
            if ($moved) {
                if ($before->terminal() || $status->terminal()) {
                    throw ValidationException::withMessages(['status' => __('booking.terminal')]);
                }
                $status = AppointmentStatus::Rescheduled;
            }
            if ($before->terminal() && $status !== $before) {
                throw ValidationException::withMessages(['status' => __('booking.terminal')]);
            }
            if ($moved && in_array($status->value, AppointmentStatus::active(), true)) {
                $this->validateSlot($wash, $date, $time, $locked->id);
            }
            $locked->fill(['date' => $date, 'time' => $time, 'approved' => $status->value])->save();

            return $locked->fresh();
        });
    }

    private function validateSlot(CarWash $wash, string $date, string $time, ?int $except = null): void
    {
        $at = Carbon::parse($date.' '.$time, config('app.timezone'));
        if ($at->lte(now())) {
            throw ValidationException::withMessages(['time' => __('booking.past')]);
        }
        $clock = $at->format('H:i:s');
        $start = substr($wash->work_time_start, 0, 5).':00';
        $end = substr($wash->work_time_end, 0, 5).':00';
        $allDay = $start === '00:00:00' && $end === '00:00:00';
        $open = $start < $end ? $clock >= $start && $clock < $end : $clock >= $start || $clock < $end;
        if (! $allDay && (! $open || $start === $end)) {
            throw ValidationException::withMessages(['time' => __('booking.closed')]);
        }
        if (Appointment::query()->where('car_wash_id', $wash->id)->whereDate('date', $at->toDateString())
            ->whereTime('time', $clock)->whereIn('approved', AppointmentStatus::active())
            ->when($except, fn ($query) => $query->whereKeyNot($except))->exists()) {
            throw ValidationException::withMessages(['time' => __('booking.occupied')]);
        }
    }
}
