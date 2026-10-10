<?php

namespace App\Services\Sms;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsOfficeGateway implements SmsGateway
{
    public function send(SmsMessage $message): SmsResult
    {
        $key = (string) config('sms.office.key');
        $sender = (string) config('sms.office.sender');

        if ($key === '') {
            return new SmsResult(SmsStatus::Failed, $message->reference, 500, 'missing_key');
        }

        if (! $this->validSender($sender)) {
            return new SmsResult(SmsStatus::Failed, $message->reference, 110, 'sender');
        }

        if (! preg_match('/^9955\d{8}$/', $message->destination)) {
            return new SmsResult(SmsStatus::Failed, $message->reference, 76, 'destination');
        }

        $length = mb_strlen($message->content);
        if ($length < 1 || $length > 1000) {
            return new SmsResult(SmsStatus::Failed, $message->reference, $length > 1000 ? 40 : 60, 'content');
        }

        if (! preg_match('/^[A-Za-z0-9]{1,20}$/', $message->reference)) {
            return new SmsResult(SmsStatus::Failed, $message->reference, null, 'reference');
        }

        $payload = [
            'key' => $key,
            'destination' => $message->destination,
            'sender' => $sender,
            'content' => $message->content,
            'reference' => $message->reference,
        ];

        if (config('sms.office.urgent') === true) {
            $payload['urgent'] = 'true';
        }

        try {
            $response = Http::asForm()
                ->connectTimeout((int) config('sms.office.connect_timeout'))
                ->timeout((int) config('sms.office.timeout'))
                ->post((string) config('sms.office.url'), $payload);
        } catch (ConnectionException) {
            Log::warning('sms_office_unknown', ['reference' => $message->reference]);

            return new SmsResult(SmsStatus::Unknown, $message->reference, null, 'timeout');
        }

        $body = $response->json();
        if (! is_array($body) || ! array_key_exists('Success', $body) || ! array_key_exists('ErrorCode', $body)) {
            Log::warning('sms_office_unexpected', [
                'reference' => $message->reference,
                'http' => $response->status(),
            ]);

            return new SmsResult(SmsStatus::Failed, $message->reference, null, 'unexpected');
        }

        $errorCode = (int) $body['ErrorCode'];
        $accepted = ($body['Success'] === true || $body['Success'] === 1 || $body['Success'] === 'true')
            && $errorCode === 0
            && $response->successful();

        Log::info('sms_office_result', [
            'reference' => $message->reference,
            'http' => $response->status(),
            'error_code' => $errorCode,
            'accepted' => $accepted,
        ]);

        if ($accepted) {
            return new SmsResult(SmsStatus::Accepted, $message->reference, 0);
        }

        return new SmsResult(SmsStatus::Failed, $message->reference, $errorCode, 'provider');
    }

    private function validSender(string $sender): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9.\-]{1,11}$/', $sender);
    }
}
