<?php

namespace App\Console\Commands;

use App\Services\Sms\SmsInbox;
use Illuminate\Console\Command;

class PruneSmsInbox extends Command
{
    protected $signature = 'sms:inbox-prune';

    protected $description = 'Remove expired mock SMS texts from the admin test inbox';

    public function handle(): int
    {
        $count = SmsInbox::prune();
        $this->info("Pruned {$count} expired inbox text(s).");

        return self::SUCCESS;
    }
}
