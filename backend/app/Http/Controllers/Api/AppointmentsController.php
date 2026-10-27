<?php

namespace App\Http\Controllers\Api;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\CarWash;
use App\Models\CarWashService;
use App\Models\User;
use App\Models\UserPackage;
use App\Services\BookingService;
use App\Services\CorporateQr;
use App\Support\Phone;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AppointmentsController extends Controller
{
    public function myAppointments()
    {
        $user = auth()->user();
        $appointments = $user->appointments()->get();
        $appointments = $appointments->map(function ($appointment) {
            $appointment->services = $appointment->services()->get();
            $appointment->car = $appointment->car;

            return $appointment;
        });

        return response()->json(['appointments' => $appointments]);
    }

    public function setStatus(Request $request, Appointment $appointment)
    {
        if (! $this->managerOwnsAppointment($appointment)) {
            return response()->json(['success' => false, 'error' => 'Forbidden'], 403);
        }
        $data = $request->validate([
            'status' => ['sometimes', 'integer', 'between:0,5'],
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'time' => ['sometimes', 'date_format:H:i'],
        ]);
        $oldDate = $appointment->date;
        $oldTime = substr($appointment->time, 0, 5);
        try {
            $appointment = app(BookingService::class)->update($appointment, $data);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'error' => collect($e->errors())->flatten()->first()], 422);
        }
        $moved = $appointment->date !== $oldDate || substr($appointment->time, 0, 5) !== $oldTime;
        $status = (int) $appointment->approved;
        $date = Carbon::parse($appointment->date)->format('d.m.Y');
        $time = substr($appointment->time, 0, 5);
        if ($moved) {
            $appointment->user->sendPush(
                ['en' => 'Appointment rescheduled', 'ru' => 'Запись перенесена', 'ka' => 'ჩანაწერის დრო შეიცვალა'],
                ['en' => "Your appointment is now $date, $time.", 'ru' => "Новое время записи: $date, $time.", 'ka' => "ჯავშნის ახალი დროა $date, $time."]
            );
        } elseif ($status === AppointmentStatus::Confirmed->value) {
            $appointment->user->sendPush(
                ['en' => 'Appointment confirmed', 'ru' => 'Запись подтверждена', 'ka' => 'ჩანაწერი დადასტურებულია'],
                ['en' => "Your appointment for $date, $time is confirmed.", 'ru' => "Запись на $date, $time подтверждена.", 'ka' => "ჯავშანი $date, $time-ზე დადასტურებულია."]
            );
        } elseif ($status === AppointmentStatus::Cancelled->value) {
            $appointment->user->sendPush(
                ['en' => 'Appointment cancelled', 'ru' => 'Запись отменена', 'ka' => 'ჯავშანი გაუქმებულია'],
                ['en' => 'Your appointment was cancelled.', 'ru' => 'Ваша запись отменена.', 'ka' => 'თქვენი ჯავშანი გაუქმებულია.']
            );
        }

        return response()->json(['success' => true, 'status' => $status]);
    }

    public function addAppointment(Request $request)
    {
        $data = $request->validate([
            'car_wash_id' => ['required', 'integer', 'exists:car_washes,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'service_id' => ['required', 'integer', 'exists:car_wash_services,id'],
            'car_id' => ['required', 'integer', 'exists:user_cars,id'],
        ]);
        try {
            $appointment = app(BookingService::class)->create((int) $request->user()->id, $data + ['services' => [$data['service_id']]]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'error' => collect($e->errors())->flatten()->first()], 422);
        }
        $date = Carbon::parse($appointment->date)->format('d.m.Y');
        $appointment->washing->manager?->sendPush(
            ['en' => 'New appointment', 'ru' => 'Новая запись', 'ka' => 'ახალი ჯავშანი'],
            ['en' => "New appointment: $date, {$appointment->time}.", 'ru' => "Новая запись: $date, {$appointment->time}.", 'ka' => "ახალი ჯავშანი: $date, {$appointment->time}."]
        );

        return response()->json(['success' => true, 'appointment' => $appointment], 200);
    }

    public function branches(Request $request)
    {

        try {

            $query = CarWash::query();

            if ($request->filled('services')) {
                $services = array_unique(
                    array_map('intval', explode(',', $request->services))
                );

                foreach ($services as $serviceId) {
                    $query->whereHas('services', function ($q) use ($serviceId) {
                        $q->whereKey($serviceId);
                    });
                }
            }

            if ($request->boolean('round')) {
                $query->where('work_time_start', '00:00:00')
                    ->where('work_time_end', '00:00:00');
            }

            if ($request->boolean('open')) {
                $now = now()->format('H:i:s');

                $query->where(function ($q) use ($now) {
                    // Круглосуточная
                    $q->where(function ($q) {
                        $q->where('work_time_start', '00:00:00')
                            ->where('work_time_end', '00:00:00');
                    })

                        // Обычный режим работы
                        ->orWhere(function ($q) use ($now) {
                            $q->where('work_time_start', '<=', $now)
                                ->where('work_time_end', '>=', $now);
                        });
                });
            }

            $branches = $query->with([
                'manager:id,name,surname,email,phone',
                'services:id,name',
            ])->get();

            $branches = $branches->map(function ($branch) {
                $manager = $branch->manager;

                return [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'address' => Phone::publicAddress($branch->address),
                    'work_start' => $branch->work_time_start,
                    'work_end' => $branch->work_time_end,
                    'phone' => Phone::format($manager?->phone),
                    'location' => $branch->location,
                    'manager' => $manager ? [
                        'id' => $manager->id,
                        'name' => $manager->name,
                        'surname' => $manager->surname,
                        'email' => $manager->email,
                        'phone' => Phone::format($manager->phone),
                    ] : null,
                    'services' => $branch->services->map(fn ($service) => [
                        'id' => $service->id,
                        'name' => $service->name,
                    ])->values(),
                ];
            });

            return response()->json([
                'success' => true,
                'branches' => $branches,
            ]);

        } catch (\Exception $e) {
            Log::warning('appointment_request_failed', ['exception' => $e::class]);

            return response()->json([
                'success' => false,
                'error' => 'Request failed',
            ], 400);
        }
    }

    public function servicesList(Request $request)
    {
        try {
            $services = CarWashService::all();
            $services = $services->map(function ($service) {
                return [
                    'id' => $service->id,
                    'name' => $service->name,
                ];
            });

            return response()->json(['success' => true, 'services' => $services]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => 'Ошибка'], 400);
        }
    }

    public function removeAppointment(Request $request, $id)
    {
        try {
            $user = auth()->user();
            $appointment = $user->appointments()->where('id', $id)->first();
            $client = $appointment->user;
            $clientLabel = $client->displayName();
            $toDate = Carbon::parse($appointment->date)->format('d.m.Y');
            $toTime = Carbon::parse($appointment->time)->format('H:i');

            $appointment->washing->manager
                ->sendPush([
                    'en' => 'Cancel an appointment',
                    'ru' => 'Отмена записи',
                    'ka' => 'შეხვედრის გაუქმება',
                ],
                    [
                        'en' => "Client {$clientLabel} canceled the record on $toDate, $toTime.",
                        'ru' => "Клиент {$clientLabel} отменил запись на $toDate, $toTime.",
                        'ka' => "კლიენტმა {$clientLabel} გააუქმა ჩანაწერი {$toDate}, {$toTime}-ზე.",
                    ], 'https://app.geocar.ge/booking');

            $appointment->delete();

            return response()->json(['success' => true, 'message' => 'Запись успешно удалена']);
        } catch (\Exception $e) {
            Log::warning('appointment_request_failed', ['exception' => $e::class]);

            return response()->json(['success' => false, 'error' => 'Request failed'], 400);
        }
    }

    public function checkQr(Request $request, $qr)
    {
        $user = auth()->user();
        if (! $user || (int) $user->role !== User::ROLE_MANAGER) {
            return response()->json(['success' => false, 'error' => 'Forbidden'], 403);
        }

        try {
            // Managers only need who/what to wash: no phone, email, dob, points, ref code.
            $userPackage = UserPackage::where('qr_code', $qr)
                ->with(['user:id,name,surname', 'car', 'package'])
                ->first();

            if (! $userPackage) {
                // Not a package: maybe a company car's code.
                $fleetCar = CorporateQr::find((string) $qr);
                if ($fleetCar === 'expired') {
                    return response()->json(['success' => false, 'error' => __('corporate.qr_expired')], 410);
                }
                if ($fleetCar) {
                    return response()->json(CorporateQr::scan($fleetCar, (int) $user->id));
                }

                return response()->json(['success' => false, 'error' => 'Not found'], 404);
            }
            if (! $userPackage->bodyAllowsWash()) {
                // No approve token: the car's body type is not confirmed, so the wash can't be approved.
                return response()->json(['success' => false, 'error' => __('cars.body_unconfirmed'), 'error_code' => 'body_unconfirmed'], 422);
            }

            // One-time, manager-bound token for POST /package/{id}/approve (10 minutes).
            $approveToken = bin2hex(random_bytes(20));
            Cache::put('package-approve:'.hash('sha256', $approveToken), [
                'manager_id' => (int) $user->id,
                'package_id' => (int) $userPackage->id,
            ], now()->addMinutes(10));

            return response()->json([
                'success' => true,
                'approve_token' => $approveToken,
                'package' => [
                    'id' => $userPackage->id,
                    'start_date' => $userPackage->start_date,
                    'end_date' => $userPackage->end_date,
                    'created_at' => $userPackage->created_at,
                    'used_washes' => (int) $userPackage->used_washes,
                    'number_of_washes' => (int) $userPackage->number_of_washes,
                    'user' => [
                        'name' => $userPackage->user?->name,
                        'surname' => $userPackage->user?->surname,
                    ],
                    'package' => [
                        'count_washes' => (int) ($userPackage->package->count_washes ?? 0),
                        'car_type' => $userPackage->package->car_type ?? null,
                    ],
                    'car' => [
                        'plate' => $userPackage->car?->plate,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::warning('qr_check_failed', ['exception' => $e::class]);

            return response()->json(['success' => false, 'error' => 'Request failed'], 400);
        }
    }

    public function allRecords(Request $request)
    {
        try {
            $user = auth()->user();
            if ($user->role == User::ROLE_MANAGER) {

                $records = $user->washing?->appointments()
                    ->with('user')
                    ->with('car')
                    ->with('services')
                    ->get();

                return response()->json(['success' => true, 'appointments' => $records]);
            } else {
                return response()->json(['success' => false, 'error' => 'Нет доступа'], 400);
            }
        } catch (\Exception $e) {
            Log::warning('appointment_request_failed', ['exception' => $e::class]);

            return response()->json(['success' => false, 'error' => 'Request failed'], 400);
        }
    }

    private function managerOwnsAppointment(Appointment $appointment): bool
    {
        $user = auth()->user();
        if (! $user || (int) $user->role !== User::ROLE_MANAGER) {
            return false;
        }

        return (int) ($user->washing?->id) === (int) $appointment->car_wash_id;
    }
}
