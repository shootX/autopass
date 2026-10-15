<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Removes /register stubs whose phone was never confirmed and that own nothing. */
class PruneUnverifiedUsers extends Command
{
    protected $signature = 'users:prune-unverified {--hours=24}';

    protected $description = 'Delete unverified registration stubs older than --hours that have no related data';

    private const OWNED = ['user_cars', 'user_packages', 'tbc_payments', 'transactions', 'appointments', 'user_vouchers', 'user_tickets', 'reviews'];

    public function handle(): int
    {
        $query = DB::table('users')
            ->where('role', User::ROLE_USER)
            ->whereNull('phone_verified_at')
            ->where('created_at', '<', now()->subHours(max(1, (int) $this->option('hours'))));

        foreach (self::OWNED as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'user_id')) {
                $query->whereNotExists(fn ($q) => $q->selectRaw('1')->from($table)->whereColumn($table.'.user_id', 'users.id'));
            }
        }
        if (Schema::hasColumn('users', 'referral_id')) {
            $query->whereNotExists(fn ($q) => $q->selectRaw('1')->from('users as r')->whereColumn('r.referral_id', 'users.id'));
        }

        $deleted = 0;
        $query->orderBy('id')->select('id')->chunkById(500, function ($rows) use (&$deleted) {
            $ids = $rows->pluck('id')->all();
            DB::transaction(function () use ($ids, &$deleted) {
                DB::table('sms_temps')->whereIn('user_id', $ids)->delete();
                $deleted += DB::table('users')->whereIn('id', $ids)->whereNull('phone_verified_at')->delete();
            });
        });

        $this->info("Pruned {$deleted} unverified users.");

        return self::SUCCESS;
    }
}
