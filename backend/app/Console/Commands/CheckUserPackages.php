<?php

namespace App\Console\Commands;

use App\Models\UserPackage;
use Carbon\Carbon;
use DragonCode\Support\Facades\Helpers\Str;
use Illuminate\Console\Command;

class CheckUserPackages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user-packages:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userPackages = UserPackage::whereDate('end_date', '<=', Carbon::now())
            ->orWhereColumn('used_washes', '>=', 'number_of_washes')
            ->get();

        foreach ($userPackages as $userPackage) {

            if($userPackage->rectoken)
            {
                if(!$userPackage->price_id)
                {
                    $userPackage->delete();
                } else {
                    //Попытка продлить
                }

                $userPackage->qr_code = Str::random(16);
                $userPackage->update();

            } else {

                $userPackage->user->sendPush([
                    'en' => 'Package expired',
                    'ru' => 'Пакет закончился',
                    'ka' => 'პაკეტი დასრულებულია',
                ], [
                    'en' => 'Your package has expired. Renew your subscription to continue using our services!',
                    'ru' => 'Срок действия вашего пакета истёк. Продлите подписку, чтобы снова пользоваться услугами!',
                    'ka' => 'თქვენი პაკეტი ვადაგასულია. განაახლეთ თქვენი გამოწერა, რათა კვლავ ისარგებლოთ მომსახურებით!'
                ]);

                $userPackage->delete();
            }

        }
        return 0;
    }
}
