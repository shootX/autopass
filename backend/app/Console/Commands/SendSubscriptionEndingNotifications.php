<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserPackage;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

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

        // Only packages that still have washes to use, on a car still in the driver's app.
        $packages = UserPackage::query()
            ->with('user')
            ->whereDate('end_date', $targetDate)
            ->withWashesLeft()
            ->whereHas('car')
            ->get();

        foreach ($packages as $package) {

            $package->user?->sendPush([
                'en' => 'The package will end soon',
                'ru' => 'Пакет скоро закончится',
                'ka' => 'პაკეტი მალე დასრულდება',
            ],
                [
                    'en' => 'Your package expires in 3 days. Renew your subscription to continue using the service!',
                    'ru' => 'Срок действия вашего пакета истекает через 3 дня. Продлите подписку, чтобы продолжить пользоваться услугами!',
                    'ka' => 'თქვენი პაკეტის ვადა 3 დღეში იწურება. სერვისით სარგებლობის გასაგრძელებლად განაახლეთ თქვენი გამოწერა!',
                ], setting: User::PUSH_RENEWALS);

        }

        return self::SUCCESS;
    }
}
