<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HttpSmsSender implements SmsSender
{
    public function send(string $destination, string $content): void
    {
        $key = (string) config('services.smsoffice.key');
        $sender = (string) config('services.smsoffice.sender');

        if ($key === '' || $sender === '') {
            Log::warning('sms_not_configured');
            throw new SmsDeliveryException('SMS provider is not configured');
        }

        $response = Http::timeout(15)->get('https://smsoffice.ge/api/v2/send/', [
            'key' => $key,
            'destination' => $destination,
            'sender' => $sender,
            'content' => $content,
        ]);

        $data = $response->json();
        $ok = $response->successful() && is_array($data) && ($data['Success'] ?? false);

        if (! $ok) {
            Log::warning('sms_delivery_failed', ['status' => $response->status()]);
            throw new SmsDeliveryException('SMS delivery failed');
        }
    }
}
