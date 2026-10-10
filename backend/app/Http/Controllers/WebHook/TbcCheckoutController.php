<?php

namespace App\Http\Controllers\WebHook;

use App\Http\Controllers\Controller;
use App\Models\TbcPayment;
use App\Services\Payments\FlittCheckout;
use App\Services\Payments\SettleTbcPayment;
use Illuminate\Http\Request;
use Throwable;

class TbcCheckoutController extends Controller
{
    public function callback(Request $request, SettleTbcPayment $settle)
    {
        $payId = (string) ($request->input('PaymentId') ?: $request->input('payId') ?: '');

        if ($payId === '') {
            return response('OK', 200);
        }

        try {
            $settle->fromPayId($payId);
        } catch (Throwable $e) {
            report($e);

            return response('RETRY', 500);
        }

        return response('OK', 200);
    }

    public function sandbox(Request $request)
    {
        $payment = $this->authorizedTestPayment($request->query('order'), $request->query('access'));
        if (! $payment) {
            return redirect($this->resultUrl(null, false));
        }
        if ($payment->status === 'succeeded') {
            return redirect($this->resultUrl($payment, true));
        }

        return view('payments.sandbox', [
            'payment' => $payment,
            'access' => (string) $request->query('access'),
        ]);
    }

    public function sandboxConfirm(Request $request, SettleTbcPayment $settle)
    {
        $payment = $this->authorizedTestPayment($request->input('order'), $request->input('access'));
        if (! $payment) {
            return redirect($this->resultUrl(null, false));
        }

        if ($request->input('decision') === 'decline') {
            if ($payment->status !== 'succeeded') {
                $payment->update(['status' => 'Failed']);
            }

            return redirect($this->resultUrl($payment->fresh(), false));
        }

        $method = (string) $request->input('method', '');
        $last4 = (string) $request->input('last4', '');
        if (! in_array($method, ['card', 'apple', 'google'], true) || ($method === 'card' && ! preg_match('/^\d{4}$/', $last4))) {
            return view('payments.sandbox', [
                'payment' => $payment,
                'access' => (string) $request->input('access'),
                'error' => 'ბარათის მონაცემები არასწორია',
            ]);
        }

        $payload = $payment->payload ?? [];
        $payload['method'] = $method;
        if ($method === 'card') {
            $payload['last4'] = $last4;
        }
        $payment->update(['payload' => $payload]);
        $payment = $settle->grantNow($payment->fresh());

        return redirect($this->resultUrl($payment->fresh(), $payment->status === 'succeeded'));
    }

    public function returned(Request $request, SettleTbcPayment $settle, FlittCheckout $flitt, FlittWebHookController $flittWebhook)
    {
        $flittOrder = (string) $request->input('order_id', '');
        if ($flittOrder !== '') {
            try {
                $payload = $flitt->status($flittOrder);
                $payment = $flittWebhook->apply($payload, $flitt, $settle);
            } catch (Throwable $e) {
                report($e);
                $payment = TbcPayment::query()->where('merchant_payment_id', $flittOrder)->first();
            }

            return redirect($this->resultUrl($payment, $payment && $payment->status === 'succeeded'));
        }

        $order = (string) $request->query('order', '');
        $payment = $order !== '' ? TbcPayment::query()->where('merchant_payment_id', $order)->first() : null;

        if ($payment) {
            try {
                $payment = $settle->fromMerchantId($payment->merchant_payment_id) ?? $payment;
            } catch (Throwable $e) {
                report($e);
            }
        }

        return redirect($this->resultUrl($payment, $payment && $payment->status === 'succeeded'));
    }

    private function authorizedTestPayment(mixed $order, mixed $access): ?TbcPayment
    {
        $order = trim((string) $order);
        $access = trim((string) $access);
        if ($order === '' || $access === '') {
            return null;
        }

        $payment = TbcPayment::query()->where('merchant_payment_id', $order)->first();
        if (! $payment || ! $payment->test_mode || ! is_string($payment->access_hash)) {
            return null;
        }

        if (! $payment->access_expires_at || $payment->access_expires_at->isPast()) {
            return null;
        }

        if (! hash_equals($payment->access_hash, hash('sha256', $access))) {
            return null;
        }

        return $payment;
    }

    private function findOrder(string $order): ?TbcPayment
    {
        if ($order === '') {
            return null;
        }

        return TbcPayment::query()->where('merchant_payment_id', $order)->first();
    }

    private function resultUrl(?TbcPayment $payment, bool $ok): string
    {
        $target = rtrim((string) config('app.url'), '/').'/payment-success';
        $query = http_build_query([
            'status' => $ok ? 'success' : 'failed',
            'order_id' => $payment?->merchant_payment_id,
            'amount' => $payment ? number_format((float) $payment->amount, 2, '.', '') : null,
        ]);

        return $target.'?'.$query;
    }
}
