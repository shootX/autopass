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
    /** A driver may cancel up to this many hours before the start; later, only the branch can (owner's rule, 2026-11-04). */
    public const CANCEL_HOURS = 1;

    /**
     * One active booking per car in a calendar week (Monday to Sunday); washing without a booking stays unlimited
     * (owner's rule, 2026-11-04).
     */
    public const ACTIVE_PER_WEEK = 1;

    public function create(int $userId, array $data): Appointment
    {
        return DB::transaction(function () use ($userId, $data) {
            $wash = CarWash::query()->lockForUpdate()->findOrFail($data['car_wash_id']);
            $car = UserCar::query()->where('user_id', $userId)->lockForUpdate()->findOrFail($data['car_id']);
            $services = array_values(array_unique($data['services']));
            if ($wash->services()->whereIn('car_wash_services.id', $services)->count() !== count($services)) {
                throw ValidationException::withMessages(['service_id' => __('booking.service_unavailable')]);
            }
            $this->validateSlot($wash, $data['date'], $data['time'], null, $car->id);
            $this->validateWeek((int) $car->id, $data['date']);
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
            if ($status === AppointmentStatus::Rescheduled && ! $moved && $before !== $status) {
                throw ValidationException::withMessages(['time' => __('booking.reschedule_time_required')]);
            }
            if ($moved && in_array($status->value, AppointmentStatus::active(), true)) {
                UserCar::query()->whereKey($locked->car_id)->lockForUpdate()->firstOrFail();
                $this->validateSlot($wash, $date, $time, $locked->id, $locked->car_id);
                $this->validateWeek((int) $locked->car_id, $date, (int) $locked->id);
            }
            $locked->fill(['date' => $date, 'time' => $time, 'approved' => $status->value])->save();

            return $locked->fresh();
        });
    }

    /**
     * The driver cancels their own booking: kept as cancelled (the history stays), only while it is still on and
     * at least CANCEL_HOURS before its start.
     */
    public function cancelByDriver(Appointment $appointment): Appointment
    {
        return DB::transaction(function () use ($appointment) {
            $locked = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            if (! in_array((int) $locked->approved, AppointmentStatus::active(), true)) {
                throw ValidationException::withMessages(['status' => __('booking.cancel_closed')]);
            }
            $at = Carbon::parse(substr((string) $locked->date, 0, 10).' '.substr((string) $locked->time, 0, 5), config('app.timezone'));
            if ($at->copy()->subHours(self::CANCEL_HOURS)->lt(now())) {
                throw ValidationException::withMessages(['time' => __('booking.cancel_too_late', ['hours' => self::CANCEL_HOURS])]);
            }
            $locked->forceFill(['approved' => AppointmentStatus::Cancelled->value])->save();

            return $locked;
        });
    }

    /** The car's lock is held by the caller, so two requests can't both pass. */
    private function validateWeek(int $carId, string $date, ?int $except = null): void
    {
        $day = Carbon::parse($date, config('app.timezone'));
        $taken = Appointment::query()->where('car_id', $carId)
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except))
            ->whereIn('approved', AppointmentStatus::active())
            ->whereDate('date', '>=', $day->copy()->startOfWeek(Carbon::MONDAY)->toDateString())
            ->whereDate('date', '<=', $day->copy()->endOfWeek(Carbon::SUNDAY)->toDateString())
            ->count();
        if ($taken >= self::ACTIVE_PER_WEEK) {
            throw ValidationException::withMessages(['date' => __('booking.week_limit')]);
        }
    }

    private function validateSlot(CarWash $wash, string $date, string $time, ?int $except = null, ?int $carId = null): void
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
        if (Appointment::query()->where(fn ($query) => $query->where('car_wash_id', $wash->id)->when($carId, fn ($query) => $query->orWhere('car_id', $carId)))
            ->whereDate('date', $at->toDateString())
            ->whereTime('time', $clock)->whereIn('approved', AppointmentStatus::active())
            ->when($except, fn ($query) => $query->whereKeyNot($except))->exists()) {
            throw ValidationException::withMessages(['time' => __('booking.occupied')]);
        }
    }
}
