<?php

namespace App\Console\Commands;

use App\Models\TbcPayment;
use App\Services\Payments\FlittCheckout;
use App\Services\Payments\SettleTbcPayment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Asks Flitt about card payments still pending after their page closed. Flitt's callback normally settles a
 * payment; when it was lost or failed (or the app closed before the return page), this grants what was paid
 * and closes what expired, so no paid package or points wait for someone to notice.
 */
class ReconcilePayments extends Command
{
    protected $signature = 'payments:reconcile {--hours=168 : how far back to look}';

    protected $description = 'Settle pending card payments from the status Flitt reports';

    public function handle(FlittCheckout $flitt, SettleTbcPayment $settle): int
    {
        $checked = 0;
        $settled = 0;
        TbcPayment::query()
            ->where('status', 'pending')
            ->where('test_mode', false)
            ->whereIn('type', ['package', 'points'])
            // A page still open may be paid any moment; past its lifetime Flitt has the final word.
            ->where('created_at', '<', now()->subMinutes(TbcPayment::CHECKOUT_MINUTES + 5))
            ->where(fn ($query) => $query->where('created_at', '>', now()->subHours(max(1, (int) $this->option('hours'))))
                ->orWhere(fn ($query) => $query->where('type', 'points')->whereNotNull('payload->promo_code_id')))
            ->orderBy('id')
            ->chunkById(50, function ($payments) use ($flitt, $settle, &$checked, &$settled) {
                foreach ($payments as $payment) {
                    $checked++;
                    try {
                        $result = $settle->fromFlittOrUnopened($payment, $flitt);
                        if ($result?->status === 'succeeded') {
                            $settled++;
                        }
                    } catch (Throwable $e) {
                        // Left pending for the next run; the log names it for a person to look at.
                        Log::warning('payment_reconcile_failed', ['payment_id' => $payment->id, 'exception' => $e::class]);
                    }
                }
            });

        $this->info("Checked {$checked}, settled {$settled}.");

        return self::SUCCESS;
    }
}
