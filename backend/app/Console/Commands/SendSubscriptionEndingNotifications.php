<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use App\Models\UserPackage;
use Carbon\Carbon;

#[Signature('app:send-subscription-ending-notifications')]
#[Description('Command description')]
class SendSubscriptionEndingNotifications extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $targetDate = Carbon::now()->addDays(3)->toDateString();

        $packages = UserPackage::whereDate('end_date', $targetDate)
            ->get();

        foreach($packages as $package) {

            $package->user->sendPush([
                'en' => 'The package will end soon',
                'ru' => 'Пакет скоро закончится',
                'ka' => 'პაკეტი მალე დასრულდება'
            ],
            [
                'en' => 'Your package expires in 3 days. Renew your subscription to continue using the service!',
                'ru' => 'Срок действия вашего пакета истекает через 3 дня. Продлите подписку, чтобы продолжить пользоваться услугами!',
                'ka' => 'თქვენი პაკეტის ვადა 3 დღეში იწურება. სერვისით სარგებლობის გასაგრძელებლად განაახლეთ თქვენი გამოწერა!'
            ]);

        }

        return self::SUCCESS;
    }
}
