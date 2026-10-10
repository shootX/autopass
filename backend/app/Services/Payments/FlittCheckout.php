<?php

namespace App\Services\Payments;

use App\Models\TbcPayment;
use Flitt\Checkout;
use Flitt\Configuration;
use Flitt\Helper\ResultHelper;
use Flitt\Order;
use RuntimeException;

class FlittCheckout
{
    public function checkoutUrl(TbcPayment $payment, string $description): string
    {
        $this->configure();

        $data = Checkout::url([
            'order_id' => $payment->merchant_payment_id,
            'order_desc' => mb_substr($description, 0, 100),
            'currency' => 'GEL',
            'amount' => $this->minor($payment),
            'response_url' => route('payment.return'),
            'server_callback_url' => route('callback.flitt.payment'),
            'merchant_data' => $payment->merchant_payment_id,
        ]);

        $url = $data->getUrl();
        if (! is_string($url) || $url === '') {
            throw new RuntimeException('Flitt did not return a checkout link');
        }

        return $url;
    }

    public function status(string $orderId): array
    {
        $this->configure();
        $data = Order::status(['order_id' => $orderId])->getData();

        return is_array($data) ? $data : [];
    }

    public function valid(array $payload): bool
    {
        $this->configure();

        return ResultHelper::isPaymentValid($payload)
            && (string) ($payload['merchant_id'] ?? '') === (string) Configuration::getMerchantId();
    }

    public function minor(TbcPayment $payment): int
    {
        return (int) round(((float) $payment->amount) * 100);
    }

    public function configure(): void
    {
        $merchantId = (string) config('services.flitt.merchant_id');
        $secret = (string) config('services.flitt.secret');

        if ($merchantId === '' || $secret === '') {
            throw new RuntimeException('Payment provider is not configured');
        }

        Configuration::setMerchantId($merchantId);
        Configuration::setSecretKey($secret);
    }
}
