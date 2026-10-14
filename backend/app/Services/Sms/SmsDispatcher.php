<?php

namespace App\Services\Sms;

use App\Models\SmsDispatch;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class SmsDispatcher
{
    public function __construct(private SmsGateway $gateway)
    {
    }

    public function send(SmsMessage $message): SmsResult
    {
        $storeBody = config('sms.driver') === 'mock' && config('sms.mock_inbox_enabled') === true;
        $expires = now()->addMinutes(min(10, max(1, (int) config('security.sms_ttl_minutes'))));
        if ($message->expiresAt !== null) {
            $messageExpiry = \Illuminate\Support\Carbon::parse($message->expiresAt);
            if ($messageExpiry->lt($expires)) {
                $expires = $messageExpiry;
            }
        }

        try {
            $row = SmsDispatch::query()->create([
                'reference' => $message->reference,
                'purpose' => $message->purpose,
                'phone_mask' => $message->maskedDestination(),
                'status' => 'pending',
                'body_encrypted' => $storeBody ? Crypt::encryptString($message->content) : null,
                'expires_at' => $expires,
            ]);
        } catch (UniqueConstraintViolationException) {
            return $this->stored($message->reference);
        }

        try {
            $result = $this->gateway->send($message);
        } catch (\Throwable $e) {
            Log::warning('sms_gateway_failed', [
                'reference' => $message->reference,
                'exception' => $e::class,
            ]);
            $result = new SmsResult(SmsStatus::Unknown, $message->reference, null, 'exception');
        }
        $row->update([
            'status' => $result->status->value,
            'error_code' => $result->errorCode,
        ]);

        if ($result->status === SmsStatus::Failed || $result->status === SmsStatus::Unknown) {
            Log::warning('sms_not_accepted', [
                'reference' => $message->reference,
                'purpose' => $message->purpose,
                'status' => $result->status->value,
                'error_code' => $result->errorCode,
            ]);
        }

        return $result;
    }

    private function stored(string $reference): SmsResult
    {
        $row = SmsDispatch::query()->where('reference', $reference)->first();
        $status = SmsStatus::tryFrom((string) $row?->status) ?? SmsStatus::Unknown;
        if ($status === SmsStatus::tryFrom('pending') || $row?->status === 'pending') {
            $status = SmsStatus::Unknown;
        }

        return new SmsResult($status, $reference, $row?->error_code, 'duplicate');
    }
}
