<?php

namespace App\Services\Payments;

use App\Models\TbcPayment;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TbcCheckout
{
    public function configured(): bool
    {
        return filled(config('services.tbc.api_key'))
            && filled(config('services.tbc.client_id'))
            && filled(config('services.tbc.client_secret'));
    }

    public function start(TbcPayment $payment, string $description, bool $saveCard = false): string
    {
        try {
            return $this->create($payment, $description, $saveCard);
        } catch (RuntimeException $e) {
            if ($saveCard && ! filled($payment->pay_id)) {
                return $this->create($payment, $description, false);
            }

            throw $e;
        }
    }

    private function create(TbcPayment $payment, string $description, bool $saveCard): string
    {
        $body = [
            'amount' => [
                'currency' => 'GEL',
                'total' => round((float) $payment->amount, 2),
            ],
            'returnurl' => route('payment.return', ['order' => $payment->merchant_payment_id]),
            'callbackUrl' => route('callback.tbc.payment'),
            'merchantPaymentId' => $payment->merchant_payment_id,
            'language' => 'KA',
            'expirationMinutes' => 15,
            'preAuth' => false,
            'saveCard' => $saveCard,
            'description' => mb_substr($description, 0, 30),
            'methods' => [5, 9, 14],
        ];

        $response = $this->request('post', '/v1/tpay/payments', $body);
        $payId = (string) ($response['payId'] ?? '');
        $url = $this->approvalUrl($response);

        if ($payId === '' || $url === null) {
            throw new RuntimeException($response['developerMessage'] ?? $response['userMessage'] ?? 'TBC did not return a checkout link');
        }

        $payment->update([
            'pay_id' => $payId,
            'status' => (string) ($response['status'] ?? 'Created'),
        ]);

        return $url;
    }

    public function details(string $payId): array
    {
        return $this->request('get', '/v1/tpay/payments/'.rawurlencode($payId));
    }

    private function approvalUrl(array $response): ?string
    {
        foreach ($response['links'] ?? [] as $link) {
            if (($link['rel'] ?? '') === 'approval_url' && filled($link['uri'] ?? null)) {
                return $link['uri'];
            }
        }

        foreach ($response['links'] ?? [] as $link) {
            if (strtoupper((string) ($link['method'] ?? '')) === 'REDIRECT' && filled($link['uri'] ?? null)) {
                return $link['uri'];
            }
        }

        return null;
    }

    private function request(string $method, string $path, ?array $json = null): array
    {
        $token = $this->token();
        $pending = Http::withToken($token)
            ->withHeaders(['apikey' => config('services.tbc.api_key')])
            ->acceptJson()
            ->timeout(20);

        $url = rtrim((string) config('services.tbc.base_url'), '/').$path;
        $response = $method === 'get' ? $pending->get($url) : $pending->post($url, $json ?? []);

        if ($response->status() === 401) {
            Cache::forget($this->tokenKey());
            $token = $this->token();
            $pending = Http::withToken($token)
                ->withHeaders(['apikey' => config('services.tbc.api_key')])
                ->acceptJson()
                ->timeout(20);
            $response = $method === 'get' ? $pending->get($url) : $pending->post($url, $json ?? []);
        }

        if ($response->failed()) {
            $message = $response->json('developerMessage')
                ?: $response->json('detail')
                ?: $response->json('title')
                ?: 'TBC payment request failed';
            throw new RuntimeException($message);
        }

        return $response->json() ?? [];
    }

    private function token(): string
    {
        return Cache::remember($this->tokenKey(), 82800, function () {
            try {
                $response = Http::asForm()
                    ->withHeaders(['apikey' => config('services.tbc.api_key')])
                    ->acceptJson()
                    ->timeout(20)
                    ->post(rtrim((string) config('services.tbc.base_url'), '/').'/v1/tpay/access-token', [
                        'client_id' => config('services.tbc.client_id'),
                        'client_secret' => config('services.tbc.client_secret'),
                    ])
                    ->throw();
            } catch (RequestException $e) {
                $message = $e->response?->json('detail') ?: 'TBC access token was rejected';
                throw new RuntimeException($message);
            }

            $token = (string) $response->json('access_token');
            if ($token === '') {
                throw new RuntimeException('TBC access token is empty');
            }

            return $token;
        });
    }

    private function tokenKey(): string
    {
        return 'tbc.token.'.substr(hash('sha256', (string) config('services.tbc.client_id')), 0, 16);
    }
}
