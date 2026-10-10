<?php

namespace App\Services\Payments;

use App\Models\Package;
use App\Models\TbcPayment;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserPackage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SettleTbcPayment
{
    public function __construct(private TbcCheckout $tbc)
    {
    }

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
        $payload = $payment->payload;
        $package = Package::query()->find($payload['package_id'] ?? 0);
        if (! $package) {
            throw new \RuntimeException('Package for this payment no longer exists');
        }

        $exists = UserPackage::query()
            ->where('user_id', $payment->user_id)
            ->where('user_car_id', $payload['car_id'] ?? 0)
            ->where('package_id', $package->id)
            ->where('created_at', '>=', $payment->created_at)
            ->exists();

        if ($exists) {
            return;
        }

        UserPackage::create([
            'user_id' => $payment->user_id,
            'user_car_id' => $payload['car_id'],
            'package_id' => $package->id,
            'price_id' => $payload['price_id'] ?? null,
            'start_date' => now(),
            'end_date' => now()->addMonths((int) ($payload['month'] ?? 1)),
            'number_of_washes' => $package->count_washes,
            'used_washes' => 0,
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
