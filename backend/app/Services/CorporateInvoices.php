<?php

namespace App\Services;

use App\Models\CorporateClient;
use App\Models\CorporateInvoice;
use App\Models\CorporatePackage;
use App\Models\CorporateWash;
use App\Models\CorporateWashMove;
use App\Models\FleetCar;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A company's invoices. A wash-package invoice is issued for one of its wash packages (the company
 * orders it in the panel, or the admin issues it); paying it credits the package's washes once. A
 * discount invoice is made at month end for the discount washes: the full price minus what the
 * drivers paid on site, billed after the service.
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
            $open = $locked->invoices()
                ->where('kind', CorporateInvoice::KIND_WASHES)
                ->where('status', CorporateInvoice::STATUS_ISSUED)
                ->count();
            if ($by === 'company' && $open >= (int) config('invoices.open_limit')) {
                return 'too_many_open';
            }

            return self::create($locked, [
                'kind' => CorporateInvoice::KIND_WASHES,
                'corporate_package_id' => $package->id,
                'title' => $package->title,
                'washes' => (int) $package->washes,
                'amount' => $package->price,
                'ordered_by' => $by,
            ]);
        });
    }

    /**
     * Month end: one discount invoice per company for the month that starts at $month, billing every
     * discount wash made before the next month that no invoice billed yet. Returns how many were made.
     */
    public static function issueMonth(CarbonInterface $month): int
    {
        $period = $month->copy()->startOfMonth();
        $before = $period->copy()->addMonth();
        $companies = CorporateWash::query()
            ->where('mode', FleetCar::MODE_DISCOUNT)
            ->whereNull('corporate_invoice_id')
            ->where('created_at', '<', $before)
            ->distinct()
            ->pluck('corporate_client_id');

        return CorporateClient::query()->whereKey($companies)->get()
            ->filter(fn (CorporateClient $client) => self::issueDiscounts($client, $period, $before) !== null)
            ->count();
    }

    /**
     * Bills the company's discount washes made before $before that no invoice has billed yet: per wash
     * the full price minus what the driver paid. Null when there is nothing to bill.
     */
    public static function issueDiscounts(CorporateClient $client, CarbonInterface $period, CarbonInterface $before): ?CorporateInvoice
    {
        return DB::transaction(function () use ($client, $period, $before) {
            // The company row lock keeps two runs from billing the same washes.
            $locked = CorporateClient::query()->whereKey($client->id)->lockForUpdate()->firstOrFail();
            $washes = CorporateWash::query()
                ->with('package:id,title')
                ->where('corporate_client_id', $locked->id)
                ->where('mode', FleetCar::MODE_DISCOUNT)
                ->whereNull('corporate_invoice_id')
                ->whereNotNull('full_price')
                ->where('created_at', '<', $before)
                ->orderBy('id')
                ->get();
            $lines = self::discountLines($washes);
            $cents = array_sum(array_column($lines, 'sum_cents'));
            if ($cents <= 0) {
                return null;
            }

            $invoice = self::create($locked, [
                'kind' => CorporateInvoice::KIND_DISCOUNT,
                'period' => $period->copy()->startOfMonth()->toDateString(),
                'title' => __('admin.inv_discount_title', ['month' => $period->format('m.Y')]),
                'washes' => $washes->count(),
                'amount' => number_format($cents / 100, 2, '.', ''),
                'lines' => array_map(fn (array $line) => array_diff_key($line, ['sum_cents' => true]), $lines),
                'ordered_by' => 'system',
            ]);
            CorporateWash::query()->whereKey($washes->modelKeys())->update(['corporate_invoice_id' => $invoice->id]);

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

            // A discount invoice bills washes already made; only a wash-package invoice credits washes.
            if (! $locked->isDiscount()) {
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
            }
            $locked->forceFill(['status' => CorporateInvoice::STATUS_PAID, 'paid_at' => now(), 'paid_by' => $adminId])->save();

            return 'ok';
        });
    }

    /**
     * An unpaid invoice can be cancelled by the admin, and a wash-package one also by the company that
     * ordered it. A cancelled discount invoice frees its washes for the next month-end run.
     *
     * @return 'ok'|'not_open'
     */
    public static function cancel(CorporateInvoice $invoice): string
    {
        return DB::transaction(function () use ($invoice) {
            $done = CorporateInvoice::query()
                ->whereKey($invoice->id)
                ->where('status', CorporateInvoice::STATUS_ISSUED)
                ->update(['status' => CorporateInvoice::STATUS_CANCELLED, 'cancelled_at' => now(), 'updated_at' => now()]);
            if ($done !== 1) {
                return 'not_open';
            }
            CorporateWash::query()->where('corporate_invoice_id', $invoice->id)->update(['corporate_invoice_id' => null]);

            return 'ok';
        });
    }

    /** One invoice row for the given fields, with the VAT rate, both parties, the due date and the number. */
    private static function create(CorporateClient $client, array $fields): CorporateInvoice
    {
        $invoice = new CorporateInvoice;
        $invoice->forceFill($fields + [
            'corporate_client_id' => $client->id,
            'vat_rate' => max(0, min(100, (int) config('invoices.vat_rate'))),
            'status' => CorporateInvoice::STATUS_ISSUED,
            'buyer' => self::buyer($client),
            'seller' => (array) config('invoices.seller'),
            'due_date' => now()->addDays(max(0, (int) config('invoices.due_days')))->toDateString(),
        ])->save();
        $invoice->forceFill(['number' => config('invoices.prefix').'-'.str_pad((string) $invoice->id, 6, '0', STR_PAD_LEFT)])->save();

        return $invoice;
    }

    /**
     * The washes grouped by package, body type, full price, percent and driver's share; each line keeps
     * the company's share per wash and in total, counted in tetri.
     *
     * @param  Collection<int, CorporateWash>  $washes
     * @return list<array{package: ?string, car_type: ?string, full_price: string, percent: int, to_pay: string, count: int, share: string, sum: string, sum_cents: int}>
     */
    private static function discountLines(Collection $washes): array
    {
        $lines = [];
        foreach ($washes as $wash) {
            $share = (int) round((float) $wash->full_price * 100) - (int) round((float) $wash->to_pay * 100);
            $key = implode('|', [$wash->corporate_package_id, $wash->car_type, $wash->full_price, $wash->discount_percent, $wash->to_pay]);
            $lines[$key] ??= [
                'package' => $wash->package?->title,
                'car_type' => $wash->car_type,
                'full_price' => (string) $wash->full_price,
                'percent' => (int) $wash->discount_percent,
                'to_pay' => (string) $wash->to_pay,
                'count' => 0,
                'share' => number_format($share / 100, 2, '.', ''),
                'sum_cents' => 0,
            ];
            $lines[$key]['count']++;
            $lines[$key]['sum_cents'] += $share;
        }

        return array_values(array_map(fn (array $line) => $line + ['sum' => number_format($line['sum_cents'] / 100, 2, '.', '')], $lines));
    }

    private static function buyer(CorporateClient $client): array
    {
        return $client->only([
            'name', 'legal_form', 'identification_code', 'legal_address', 'bank_name', 'bank_code', 'bank_account',
            'contact', 'phone', 'email', 'vat_payer',
        ]);
    }
}
