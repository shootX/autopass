<?php

namespace App\Services\Payments;

use App\Models\Package;
use App\Models\PackagePrice;
use App\Models\PromoCode;
use App\Models\PromoCodeUse;
use App\Models\TbcPayment;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserPackage;
use App\Services\Points\ReferralReward;
use App\Support\TestPayments;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SettleTbcPayment
{
    public function __construct(private TbcCheckout $tbc) {}

    public function fromPayId(string $payId): ?TbcPayment
    {
        $payment = TbcPayment::query()->where('pay_id', $payId)->first();
        if (! $payment) {
            return null;
        }

        return $this->confirm($payment);
    }

    public function fromMerchantId(string $merchantPaymentId): ?TbcPayment
    {
        $payment = TbcPayment::query()->where('merchant_payment_id', $merchantPaymentId)->first();
        if (! $payment || ! filled($payment->pay_id)) {
            return $payment;
        }

        return $this->confirm($payment);
    }

    /** A Flitt order status (a signed callback, or the status API): grants a paid order, closes a failed one. */
    public function fromFlitt(array $payload, FlittCheckout $flitt): ?TbcPayment
    {
        $payment = TbcPayment::query()
            ->where('merchant_payment_id', (string) ($payload['order_id'] ?? ''))
            ->first();

        if (! $payment) {
            return null;
        }

        if ((int) ($payload['amount'] ?? -1) !== $flitt->minor($payment)) {
            return $this->close($payment, 'amount_mismatch');
        }

        $status = (string) ($payload['order_status'] ?? '');
        if ($status === 'approved') {
            return $this->grantNow($payment);
        }

        // A reversal after success changes nothing: money and points are not taken back (owner's rule, 2026-11-03).
        if (in_array($status, ['declined', 'expired', 'reversed'], true)) {
            return $this->close($payment, $status);
        }

        return $payment->fresh();
    }

    /**
     * Writes a failed status under the same row lock the grant takes, and never over a paid payment: an older
     * answer arriving late can't turn a success into a failure (and let a later approval grant it again).
     */
    private function close(TbcPayment $payment, string $status): TbcPayment
    {
        return DB::transaction(function () use ($payment, $status) {
            $locked = TbcPayment::query()->whereKey($payment->id)->lockForUpdate()->first();
            if (! $locked) {
                return $payment;
            }
            if ($locked->status !== 'succeeded' && $locked->paid_at === null) {
                $locked->update(['status' => $status]);
            }

            return $locked->fresh();
        });
    }

    public function grantNow(TbcPayment $payment): TbcPayment
    {
        return DB::transaction(function () use ($payment) {
            $locked = TbcPayment::query()->whereKey($payment->id)->lockForUpdate()->first();
            // paid_at is set once, with the grant: a payment granted before is never granted again.
            if (! $locked || $locked->status === 'succeeded' || $locked->paid_at !== null) {
                return $locked ?? $payment;
            }

            // Sandbox money is only credited if the owner is still an allowed, unbanned tester right now.
            if ($locked->test_mode) {
                $owner = User::query()->whereKey($locked->user_id)->lockForUpdate()->first();
                if (! TestPayments::allowed($owner)) {
                    $locked->update(['status' => 'Failed']);

                    return $locked->fresh();
                }
            }

            if ($locked->type === 'package') {
                $this->grantPackage($locked, '');
            } elseif ($locked->type === 'points') {
                $this->grantPoints($locked);
            }

            $locked->update([
                'status' => 'succeeded',
                'paid_at' => now(),
            ]);

            return $locked->fresh();
        });
    }

    public function confirm(TbcPayment $payment): TbcPayment
    {
        if ($payment->status === 'succeeded' || ! filled($payment->pay_id)) {
            return $payment;
        }

        $details = $this->tbc->details($payment->pay_id);
        $status = (string) ($details['status'] ?? '');

        if ($status !== 'Succeeded') {
            if (in_array($status, ['Failed', 'Expired', 'Returned', 'PartialReturned'], true)) {
                return $this->close($payment, $status);
            }

            return $payment->fresh();
        }

        $reported = isset($details['amount']) ? (float) $details['amount'] : null;
        if ($reported !== null && abs($reported - (float) $payment->amount) > 0.01) {
            return $this->close($payment, 'amount_mismatch');
        }

        return DB::transaction(function () use ($payment, $details) {
            $locked = TbcPayment::query()->whereKey($payment->id)->lockForUpdate()->first();
            if (! $locked || $locked->status === 'succeeded' || $locked->paid_at !== null) {
                return $locked ?? $payment;
            }

            if ($locked->type === 'package') {
                $this->grantPackage($locked, (string) ($details['recId'] ?? ''));
            } elseif ($locked->type === 'points') {
                $this->grantPoints($locked);
            }

            $locked->update([
                'status' => 'succeeded',
                'paid_at' => now(),
            ]);

            return $locked->fresh();
        });
    }

    private function grantPackage(TbcPayment $payment, string $recId): void
    {
        User::query()->whereKey($payment->user_id)->lockForUpdate()->firstOrFail();
        $payload = $payment->payload;
        // Paid is paid: a package taken off sale (soft-deleted) while the driver was paying is still granted.
        $package = Package::withTrashed()->find($payload['package_id'] ?? 0);
        if (! $package) {
            throw new \RuntimeException('Package for this payment no longer exists');
        }

        // A package the driver removed since still counts as granted (tbc_payment_id is unique).
        $exists = UserPackage::withTrashed()
            ->where('tbc_payment_id', $payment->id)
            ->exists();

        if ($exists) {
            return;
        }

        $discount = ($payload['type'] ?? null) === Package::TYPE_DISCOUNT;
        $washes = (int) ($payload['washes'] ?? $package->count_washes);

        UserPackage::create([
            'tbc_payment_id' => $payment->id,
            'user_id' => $payment->user_id,
            'user_car_id' => $payload['car_id'],
            'package_id' => $package->id,
            // The price row is only a reference; a month the admin stopped selling must not fail the grant.
            'price_id' => PackagePrice::query()->whereKey($payload['price_id'] ?? 0)->exists() ? $payload['price_id'] : null,
            'start_date' => now(),
            'end_date' => $discount ? now()->addDays(Package::DISCOUNT_DAYS) : now()->addMonths((int) ($payload['month'] ?? 1)),
            'number_of_washes' => $washes,
            'used_washes' => 0,
            'unlimited' => $discount && $washes === 0,
            'full_price' => $discount ? $payload['full_price'] : null,
            'discount_percent' => $discount ? (int) $payload['percent'] : null,
            'qr_code' => Str::random(16),
            'rectoken' => $recId !== '' ? $recId : null,
        ]);

        if (! $payment->test_mode) {
            ReferralReward::forPayment($payment);
        }
    }

    private function grantPoints(TbcPayment $payment): void
    {
        $amount = (int) ($payment->payload['points'] ?? round((float) $payment->amount));
        $user = User::query()->lockForUpdate()->find($payment->user_id);
        if (! $user) {
            return;
        }

        $user->points += $amount;
        if ($payment->test_mode) {
            // Sandbox points are tracked apart so they can't buy real partner goods.
            $user->test_points = (int) $user->test_points + $amount;
        }
        $user->save();

        Transaction::create([
            'user_id' => $user->id,
            'amount' => $amount,
            'type' => 'income',
            'system' => 'TBC',
            'status' => true,
            'comment' => 'Points',
        ]);

        $promoId = (int) ($payment->payload['promo_code_id'] ?? 0);
        if ($promoId > 0) {
            $this->recordPromo($payment, $promoId);
        }
    }

    /** The discount code counts as used once its payment went through (a second paid use still counts). */
    private function recordPromo(TbcPayment $payment, int $promoId): void
    {
        $promo = PromoCode::query()->lockForUpdate()->find($promoId);
        if (! $promo) {
            return;
        }
        $quote = (array) ($payment->payload['quote'] ?? []);
        $created = PromoCodeUse::query()->firstOrCreate(
            ['promo_code_id' => $promo->id, 'user_id' => $payment->user_id],
            ['tbc_payment_id' => $payment->id, 'points' => 0, 'discount' => (float) ($quote['promo_discount'] ?? 0)],
        );
        if ($created->wasRecentlyCreated) {
            $promo->increment('used_count');
        }
    }
}
