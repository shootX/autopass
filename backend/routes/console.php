<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('sms:remove')->everyMinute(); // Удаление SMS и Email кодов из базы
Schedule::command('sms:inbox-prune')->everyMinute(); // Expired mock SMS texts are wiped from the admin test inbox
Schedule::command('user-packages:check')->everyMinute(); // Проверка истечения пакетов и аннулирование
Schedule::command('app:send-subscription-ending-notifications')
    ->dailyAt('14:00'); // Напоминание за 3 дня до окончания пакета
Schedule::command('app:send-subscription-one-day-notifications')
    ->dailyAt('15:00'); // Напоминание за 1 день до окончания пакета, что он будет автоматически продлён
Schedule::command('app:send-manager-fifteen-minutes-before')
    ->everyMinute(); // Напоминание менеджеру за 15 минут до прибытия клиента
Schedule::command('users:prune-unverified')->hourly(); // /register stubs that never confirmed the phone
Schedule::command('corporate:discount-invoices')->monthlyOn(1, '01:00'); // Company discount washes of the month that ended: full price minus what drivers paid
