<?php

namespace App\Jobs;

use App\Models\SmsTemp;
use App\Services\Sms\SmsDispatcher;
use App\Services\Sms\SmsMessage;
use App\Services\Sms\SmsStatus;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends an auth SMS off the request, so the response time no longer depends on the gateway.
 * Enumeration-sensitive endpoints dispatch one for known and unknown numbers alike; the
 * decoy carries no message and does nothing. Encrypted: the payload holds the OTP.
 */
class SendSms implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    // Transient failures (e.g. a locked queue/sms DB) are retried; the dispatcher is idempotent
    // per reference, so a retry never sends a second SMS once the gateway accepted it.
    public int $tries = 3;

    /** @return list<int> seconds before the 2nd and 3rd attempt */
    public function backoff(): array
    {
        return [5, 20];
    }

    public function __construct(
        public readonly ?SmsMessage $message = null,
        public readonly ?string $challengeId = null,
    ) {}

    public static function decoy(): self
    {
        return new self;
    }

    public function handle(SmsDispatcher $dispatcher): void
    {
        if ($this->message === null) {
            return;
        }

        $result = $dispatcher->send($this->message);
        if ($result->status === SmsStatus::Failed && $this->challengeId !== null) {
            // Never delivered: drop the challenge so it neither works nor blocks a retry.
            SmsTemp::query()->where('public_id', $this->challengeId)->whereNull('consumed_at')->delete();
        }
    }
}
