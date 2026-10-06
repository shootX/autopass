<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use App\Models\UserPackage;
use Carbon\Carbon;

#[Signature('app:send-subscription-one-day-notifications')]
#[Description('Command description')]
class SendSubscriptionOneDayNotifications extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $targetDate = Carbon::now()->addDays(1)->toDateString();

        $packages = UserPackage::whereDate('end_date', $targetDate)
            ->whereNotNull('rectoken')
            ->get();

        foreach($packages as $package) {

            $package->user->sendPush([
                'en' => 'Automatic package renewal',
                'ru' => 'Автопродление пакета',
                'ka' => 'პაკეტის ავტომატური განახლება',
            ],
            [
                'en' => 'Your package will automatically renew tomorrow. If you want to cancel the auto-renewal, please do so in your personal account.',
                'ru' => 'Ваш пакет будет автоматически продлён завтра. Если вы хотите отменить автопродление, сделайте это в личном кабинете.',
                'ka' => 'თქვენი პაკეტი ხვალ ავტომატურად განახლდება. თუ გსურთ ავტომატური განახლების გაუქმება, გთხოვთ, ეს თქვენს პირად ანგარიშში გააკეთოთ.',
            ]);
        }

        return self::SUCCESS;
    }
}
