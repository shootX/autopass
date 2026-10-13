<?php

namespace App\Services\Sms;

class SmsResult
{
    public function __construct(
        public readonly SmsStatus $status,
        public readonly ?string $reference = null,
        public readonly ?int $errorCode = null,
        public readonly ?string $reason = null,
    ) {
    }

    public function ok(): bool
    {
        return $this->status === SmsStatus::Simulated || $this->status === SmsStatus::Accepted;
    }
}
