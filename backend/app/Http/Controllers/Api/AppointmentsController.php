<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppointmentsService;
use App\Models\CarWash;
use App\Models\CarWashService;
use App\Models\User;
use App\Models\UserPackage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Appointment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

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
        if (!$this->managerOwnsAppointment($appointment)) {
            return response()->json(['success' => false, 'error' => 'Forbidden'], 403);
        }

        $request->validate([
            'status' => ['sometimes', 'integer', 'between:1,5'],
            'date' => ['sometimes', 'date'],
            'time' => ['sometimes', 'date_format:H:i'],
        ]);

        if ($request->has('status')) {
            $appointment->approved = $request->status;
        }

        if ($request->has('date')) {
            $appointment->date = $request->date;
        }

        if ($request->has('time')) {
            $appointment->time = $request->time;
        }

        $appointment->update();

        if($request->status == 2) {
            //Отправить пуш уведомление клиенту
            $appointment->user->sendPush([
                'en' => 'Entry rejected',
                'ru' => 'Запись отклонена',
                'ka' => 'ჩანაწერი უარყოფილია',
            ],[
                'en' => 'Your car wash appointment has been rejected.',
                'ru' => 'Ваша запись на мойку отклонена.',
                'ka' => 'თქვენი მანქანის რეცხვაზე დანიშვნა უარყოფილია.',
            ]);
            $appointment->delete();
        }

        if($request->status == 1) {
            $toDateText = Carbon::parse($appointment->date)->format('d.m.Y');
            $toTimeText = Carbon::parse($appointment->time)->format('H:i');
            $appointment->user->sendPush([
                'en' => 'Appointment confirmed',
                'ru' => 'Запись подтверждена',
                'ka' => 'ჩანაწერი დადასტურებულია',
            ],[
                'en' => 'Your appointment for ' . $toDateText . ', ' . $toTimeText . ' has been confirmed. We look forward to seeing you!.',
                'ru' => 'Ваша запись на ' . $toDateText . ', ' . $toTimeText . ' подтверждена. Ждём вас!',
                'ka' => 'თქვენი შეხვედრა ' . $toDateText . ', ' . $toTimeText . '-სთვის დადასტურებულია. მოუთმენლად ველით თქვენს ნახვას!',
            ]);
        }

        if($request->status == 3) {
            $newDate = Carbon::parse($request->date)->format('d.m.Y');
            $newTime = Carbon::parse($request->time)->format('H:i');
            $appointment->user->sendPush([
                'en' => 'Appointment rescheduled',
                'ru' => 'Запись перенесена',
                'ka' => 'ჩანაწერის დრო შეიცვალა',
            ],[
                'en' => 'Your appointment time has been changed to ' . $newDate . ', ' . $newTime . '. If the time is inconvenient, please contact us.',
                'ru' => 'Ваше время записи изменено на ' . $newDate . ', ' . $newTime . '. Если время неудобно, свяжитесь с нами.',
                'ka' => 'თქვენი შეხვედრის დრო შეიცვალა '. $newDate. ', '. $newTime. '-ით. თუ დრო თქვენთვის შეუფერებელია, გთხოვთ, დაგვიკავშირდეთ.',
            ]);
        }

        return response()->json(['success' => true, 'status' => $request->status, 'message' => 'Статус успешно изменен']);
    }

    public function addAppointment(Request $request)
    {
        $user = auth()->user();

        $validator = Validator::make($request->all(), [
            'car_wash_id' => ['required', 'integer', 'exists:car_washes,id'],
            'date' => ['required', 'string', 'date'],
            'time' => ['required', 'string', 'date_format:H:i'],
            'service_id' => ['required', 'integer', 'exists:car_wash_services,id'],
            'car_id' => ['required', 'integer', 'exists:user_cars,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'მონაცემები არასრულია'
            ], 400);
        }

        $dateTime = Carbon::parse($request->date . ' ' . $request->time);

        if ($dateTime->isPast()) {
            return response()->json([
                'success' => false,
                'error' => 'წარსული დრო ვერ შეირჩევა.'
            ], 422);
        }

        $appointments = Appointment::where('car_wash_id', $request->car_wash_id)
            ->where('date', $request->date)
            ->where('time', $request->time)
            ->get();

        if (count($appointments) > 0) {
            return response()->json([
                'success' => false,
                'error' => 'ამ დროს თავისუფალი ადგილი არ არის'
            ], 400);
        }

        //Далее запись

        $appointment = Appointment::create([
            'user_id' => $user->id,
            'car_wash_id' => $request->car_wash_id,
            'date' => $request->date,
            'time' => $request->time,
            'qr_code' => Str::random(12),
            'approved' => false,
            'car_id' => $request->car_id
        ]);

        AppointmentsService::create([
            'appointment_id' => $appointment->id,
            'car_wash_service_id' => $request->service_id,
        ]);

        //Тут отправить пуш уведомление менеджеру
        $client = $appointment->user;
        $clientLabel = $client->displayName();
        $date = Carbon::parse($request->date)->format('d.m.Y');
        $time = Carbon::parse($request->time)->format('H:i');

        $appointment->washing->manager
            ->sendPush([
                'en' => 'New appointment for car wash',
                'ru' => 'Новая запись на мойку',
                'ka' => 'ახალი დანიშვნა ავტოსამრეცხაოში',
            ],
            [
                'en' => "Client: {$clientLabel}. Date: $date. Time: $time.",
                'ru' => "Клиент: {$clientLabel}. Дата: $date. Время: $time.",
                'ka' => "კლიენტი: {$clientLabel}. თარიღი: $date. დრო: $time.",
            ], 'https://app.geocar.ge/booking');

        return response()->json([
            'success' => true,
            'appointment' => $appointment,
            'message' => 'Вы успешно записались на мойку'
        ], 200);
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
                    'address' => \App\Support\Phone::publicAddress($branch->address),
                    'work_start' => $branch->work_time_start,
                    'work_end' => $branch->work_time_end,
                    'phone' => \App\Support\Phone::format($manager?->phone),
                    'location' => $branch->location,
                    'manager' => $manager ? [
                        'id' => $manager->id,
                        'name' => $manager->name,
                        'surname' => $manager->surname,
                        'email' => $manager->email,
                        'phone' => \App\Support\Phone::format($manager->phone),
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
            \Illuminate\Support\Facades\Log::warning('appointment_request_failed', ['exception' => $e::class]);

            return response()->json([
                'success' => false,
                'error' => 'Request failed',
            ], 400);
        }
    }

    public function servicesList(Request $request)
    {
        try{
            $services = CarWashService::all();
            $services = $services->map(function ($service) {
                return [
                    'id' => $service->id,
                    'name' => $service->name
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
            \Illuminate\Support\Facades\Log::warning('appointment_request_failed', ['exception' => $e::class]);
            return response()->json(['success' => false, 'error' => 'Request failed'], 400);
        }
    }

    public function checkQr(Request $request, $qr)
    {
        $user = auth()->user();
        if (!$user || (int) $user->role !== User::ROLE_MANAGER) {
            return response()->json(['success' => false, 'error' => 'Forbidden'], 403);
        }

        try {
            $userPackage = UserPackage::where('qr_code', $qr)
                ->with(['user', 'car', 'package'])
                ->first();

            if (! $userPackage) {
                return response()->json(['success' => false, 'error' => 'Not found'], 404);
            }

            return response()->json([
                'success' => true,
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
                        'phone' => $userPackage->user?->phone,
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
            \Illuminate\Support\Facades\Log::warning('qr_check_failed', ['exception' => $e::class]);

            return response()->json(['success' => false, 'error' => 'Request failed'], 400);
        }
    }

    public function allRecords(Request $request)
    {
        try {
            $user = auth()->user();
            if($user->role == User::ROLE_MANAGER) {

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
            \Illuminate\Support\Facades\Log::warning('appointment_request_failed', ['exception' => $e::class]);
            return response()->json(['success' => false, 'error' => 'Request failed'], 400);
        }
    }

    private function managerOwnsAppointment(Appointment $appointment): bool
    {
        $user = auth()->user();
        if (!$user || (int) $user->role !== User::ROLE_MANAGER) {
            return false;
        }

        return (int) ($user->washing?->id) === (int) $appointment->car_wash_id;
    }
}
