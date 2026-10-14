<?php

namespace App\Console\Commands;

use App\Models\UserPackage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckUserPackages extends Command
{
    protected $signature = 'user-packages:check';

    protected $description = 'Notify once about expired packages while preserving purchase and wash history';

    public function handle(): int
    {
        UserPackage::query()->whereNull('expiration_notified_at')
            ->where(fn ($query) => $query->where('end_date', '<=', now())->orWhereColumn('used_washes', '>=', 'number_of_washes'))
            ->chunkById(100, function ($packages) {
                foreach ($packages as $package) {
                    DB::transaction(function () use ($package) {
                        $locked = UserPackage::query()->lockForUpdate()->find($package->id);
                        if (! $locked || $locked->expiration_notified_at !== null) {
                            return;
                        }
                        $locked->expiration_notified_at = now();
                        $locked->save();
                        $locked->user?->sendPush(
                            ['en' => 'Package expired', 'ru' => 'Пакет закончился', 'ka' => 'პაკეტი დასრულებულია'],
                            ['en' => 'Your package has expired. You can purchase a new package.',
                                'ru' => 'Срок пакета истёк. Вы можете приобрести новый пакет.',
                                'ka' => 'პაკეტის ვადა ამოიწურა. შეგიძლიათ შეიძინოთ ახალი პაკეტი.']
                        );
                    });
                }
            });

        return self::SUCCESS;
    }
}
