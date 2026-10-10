<?php

namespace App\Http\Controllers\WebHook;

use App\Http\Controllers\Controller;
use App\Models\TbcPayment;
use App\Services\Payments\FlittCheckout;
use App\Services\Payments\SettleTbcPayment;
use Illuminate\Http\Request;
use Throwable;

class FlittWebHookController extends Controller
{
    public function callback(Request $request, FlittCheckout $flitt, SettleTbcPayment $settle)
    {
        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            $payload = $request->all();
        }
        if (isset($payload['response']) && is_array($payload['response'])) {
            $payload = $payload['response'];
        }

        try {
            if (! $flitt->valid($payload)) {
                return response('invalid signature', 400);
            }

            $this->apply($payload, $flitt, $settle);
        } catch (Throwable $e) {
            report($e);

            return response('RETRY', 500);
        }

        return response('OK', 200);
    }

    public function apply(array $payload, FlittCheckout $flitt, SettleTbcPayment $settle): ?TbcPayment
    {
        $payment = TbcPayment::query()
            ->where('merchant_payment_id', (string) ($payload['order_id'] ?? ''))
            ->first();

        if (! $payment) {
            return null;
        }

        if ((int) ($payload['amount'] ?? -1) !== $flitt->minor($payment)) {
            if ($payment->status !== 'succeeded') {
                $payment->update(['status' => 'amount_mismatch']);
            }

            return $payment;
        }

        $status = (string) ($payload['order_status'] ?? '');
        if ($status === 'approved') {
            return $settle->grantNow($payment);
        }

        if (in_array($status, ['declined', 'expired', 'reversed'], true) && $payment->status !== 'succeeded') {
            $payment->update(['status' => $status]);
        }

        return $payment->fresh();
    }
}
