<?php

namespace App\Services\Payments;

use App\Models\Package;
use App\Models\TbcPayment;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserPackage;
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

    public function grantNow(TbcPayment $payment): TbcPayment
    {
        return DB::transaction(function () use ($payment) {
            $locked = TbcPayment::query()->whereKey($payment->id)->lockForUpdate()->first();
            if (! $locked || $locked->status === 'succeeded') {
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
                $payment->update(['status' => $status]);
            }

            return $payment->fresh();
        }

        $reported = isset($details['amount']) ? (float) $details['amount'] : null;
        if ($reported !== null && abs($reported - (float) $payment->amount) > 0.01) {
            $payment->update(['status' => 'amount_mismatch']);

            return $payment->fresh();
        }

        return DB::transaction(function () use ($payment, $details) {
            $locked = TbcPayment::query()->whereKey($payment->id)->lockForUpdate()->first();
            if (! $locked || $locked->status === 'succeeded') {
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
        $package = Package::query()->find($payload['package_id'] ?? 0);
        if (! $package) {
            throw new \RuntimeException('Package for this payment no longer exists');
        }

        $exists = UserPackage::query()
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
            'price_id' => $payload['price_id'] ?? null,
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
    }
}
