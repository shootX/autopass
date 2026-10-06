<?php

namespace App\Console\Commands;

use App\Models\SmsTemp;
use App\Models\VerificationCode;
use Illuminate\Console\Command;

class RemoveOldSms extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sms:remove';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove old sms';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        SmsTemp::where('created_at', '<', now()->subHour(1))->delete();
        VerificationCode::where('created_at', '<', now()->subMinutes(10))->delete();
        return 0;
    }
}
