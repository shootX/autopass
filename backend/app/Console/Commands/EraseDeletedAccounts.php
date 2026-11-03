<?php

namespace App\Console\Commands;

use App\Services\AccountDeletion;
use Illuminate\Console\Command;

/** Erases the accounts deleted in the app more than AccountDeletion::RESTORE_DAYS ago (AccountEraser). */
class EraseDeletedAccounts extends Command
{
    protected $signature = 'accounts:erase-deleted';

    protected $description = 'Erase accounts deleted in the app more than 90 days ago';

    public function handle(): int
    {
        $this->info('Erased '.AccountDeletion::eraseDue().'.');

        return self::SUCCESS;
    }
}
