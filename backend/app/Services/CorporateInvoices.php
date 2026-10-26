<?php

namespace App\Services;

use App\Models\CorporateClient;
use App\Models\CorporateInvoice;
use App\Models\CorporatePackage;
use App\Models\CorporateWashMove;
use Illuminate\Support\Facades\DB;

/**
 * A company's invoices: issued for one of its wash packages (the company orders it in the panel,
 * or the admin issues it), then paid or cancelled. Paying credits the package's washes once.
 */
class CorporateInvoices
{
    /**
     * @param  'company'|'admin'  $by
     * @return CorporateInvoice|'not_orderable'|'too_many_open'
     */
    public static function issue(CorporateClient $client, CorporatePackage $package, string $by): CorporateInvoice|string
    {
        if (! self::orderable($client, $package)) {
            return 'not_orderable';
        }

        return DB::transaction(function () use ($client, $package, $by) {
            // The company row lock keeps two orders at once from passing the open limit.
            $locked = CorporateClient::query()->whereKey($client->id)->lockForUpdate()->firstOrFail();
            $open = $locked->invoices()->where('status', CorporateInvoice::STATUS_ISSUED)->count();
            if ($by === 'company' && $open >= (int) config('invoices.open_limit')) {
                return 'too_many_open';
            }

            $invoice = new CorporateInvoice;
            $invoice->forceFill([
                'corporate_client_id' => $locked->id,
                'corporate_package_id' => $package->id,
                'title' => $package->title,
                'washes' => (int) $package->washes,
                'amount' => $package->price,
                'vat_rate' => max(0, min(100, (int) config('invoices.vat_rate'))),
                'status' => CorporateInvoice::STATUS_ISSUED,
                'ordered_by' => $by,
                'buyer' => self::buyer($locked),
                'seller' => (array) config('invoices.seller'),
                'due_date' => now()->addDays(max(0, (int) config('invoices.due_days')))->toDateString(),
            ])->save();
            $invoice->forceFill(['number' => config('invoices.prefix').'-'.str_pad((string) $invoice->id, 6, '0', STR_PAD_LEFT)])->save();

            return $invoice;
        });
    }

    /** Only the company's own active wash packages with a price can be ordered. */
    public static function orderable(CorporateClient $client, CorporatePackage $package): bool
    {
        return (int) $package->corporate_client_id === (int) $client->id
            && $package->type === CorporatePackage::TYPE_WASHES
            && $package->is_active
            && $package->price !== null
            && (float) $package->price > 0
            && (int) $package->washes > 0;
    }

    /** Marks the invoice paid and credits its washes to the company, both or neither. @return 'ok'|'not_open' */
    public static function markPaid(CorporateInvoice $invoice, int $adminId): string
    {
        return DB::transaction(function () use ($invoice, $adminId) {
            $locked = CorporateInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $client = $locked->corporate_client_id ? CorporateClient::query()->find($locked->corporate_client_id) : null;
            if (! $locked->isOpen() || ! $client) {
                return 'not_open';
            }

            $result = CorporateWashes::credit(
                $client,
                CorporateWashMove::TYPE_CREDIT,
                (int) $locked->washes,
                $locked->package,
                __('admin.inv_credit_note', ['number' => $locked->number]),
                $adminId,
                (int) $locked->id,
            );
            if ($result !== 'ok') {
                return 'not_open';
            }
            $locked->forceFill(['status' => CorporateInvoice::STATUS_PAID, 'paid_at' => now(), 'paid_by' => $adminId])->save();

            return 'ok';
        });
    }

    /** An unpaid invoice can be cancelled by the admin or by the company that ordered it. @return 'ok'|'not_open' */
    public static function cancel(CorporateInvoice $invoice): string
    {
        $done = CorporateInvoice::query()
            ->whereKey($invoice->id)
            ->where('status', CorporateInvoice::STATUS_ISSUED)
            ->update(['status' => CorporateInvoice::STATUS_CANCELLED, 'cancelled_at' => now(), 'updated_at' => now()]);

        return $done === 1 ? 'ok' : 'not_open';
    }

    private static function buyer(CorporateClient $client): array
    {
        return $client->only([
            'name', 'legal_form', 'identification_code', 'legal_address', 'bank_name', 'bank_code', 'bank_account',
            'contact', 'phone', 'email', 'vat_payer',
        ]);
    }
}
