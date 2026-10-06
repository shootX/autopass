<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:send-manager-fifteen-minutes-before')]
#[Description('Command description')]
class SendManagerFifteenMinutesBefore extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $target = now()->addMinutes(15);

        $start = $target->copy()->startOfMinute();
        $end = $target->copy()->endOfMinute();

        $appointments = Appointment::whereRaw(
            "(date + time) BETWEEN ? AND ?",
            [$start, $end]
        )->get();

        foreach ($appointments as $appointment) {
            $client = $appointment->user;
            $userText = $client->surname." ".$client->name;
            $toTime = Carbon::parse($appointment->time)->format('H:i');
            $appointment->washing->manager->sendPush('Напоминание о записи!', "Через 15 минут клиент $userText приедет на мойку. Время: $toTime.");
        }
    }
}
