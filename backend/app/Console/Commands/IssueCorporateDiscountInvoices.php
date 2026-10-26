<?php

namespace App\Console\Commands;

use App\Services\CorporateInvoices;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class IssueCorporateDiscountInvoices extends Command
{
    protected $signature = 'corporate:discount-invoices {--month= : YYYY-MM; the month that just ended when left out}';

    protected $description = 'Bill each company the discount washes of a month: full price minus what the drivers paid';

    public function handle(): int
    {
        $month = $this->option('month');
        if ($month !== null && ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $month)) {
            $this->error('Use --month=YYYY-MM.');

            return self::INVALID;
        }
        $start = $month ? Carbon::createFromFormat('!Y-m', $month) : now()->subMonthNoOverflow()->startOfMonth();
        if ($start->gte(now()->startOfMonth())) {
            $this->error('The month is not over yet.');

            return self::INVALID;
        }

        $count = CorporateInvoices::issueMonth($start);
        $this->info("Issued {$count} discount invoice(s).");

        return self::SUCCESS;
    }
}
