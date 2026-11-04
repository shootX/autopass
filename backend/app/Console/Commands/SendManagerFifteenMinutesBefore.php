<?php

namespace App\Console\Commands;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('app:send-manager-fifteen-minutes-before')]
#[Description('Tell the branch manager about a booking 15 minutes before it starts')]
class SendManagerFifteenMinutesBefore extends Command
{
    public function handle(): int
    {
        $target = now()->addMinutes(15);

        // Only bookings still on; matched by day and minute, so it works the same on every database.
        $appointments = Appointment::query()
            ->with(['user:id,name,surname', 'washing.manager'])
            ->whereDate('date', $target->toDateString())
            ->whereTime('time', '>=', $target->copy()->startOfMinute()->format('H:i:s'))
            ->whereTime('time', '<=', $target->copy()->endOfMinute()->format('H:i:s'))
            ->whereIn('approved', AppointmentStatus::active())
            ->get();

        foreach ($appointments as $appointment) {
            // One broken booking (no client name, no manager) never stops the reminders of the others.
            try {
                $manager = $appointment->washing?->manager;
                if (! $manager) {
                    continue;
                }
                $client = trim(($appointment->user?->surname ?? '').' '.($appointment->user?->name ?? '')) ?: '—';
                $time = Carbon::parse($appointment->time)->format('H:i');
                $manager->sendPush(
                    ['en' => 'Booking reminder', 'ru' => 'Напоминание о записи', 'ka' => 'ჯავშნის შეხსენება'],
                    [
                        'en' => "In 15 minutes {$client} arrives at the wash. Time: {$time}.",
                        'ru' => "Через 15 минут клиент {$client} приедет на мойку. Время: {$time}.",
                        'ka' => "15 წუთში კლიენტი {$client} მოვა სამრეცხაოზე. დრო: {$time}.",
                    ]
                );
            } catch (Throwable $e) {
                report($e);
            }
        }

        return self::SUCCESS;
    }
}
