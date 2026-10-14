<?php

namespace App\Services\Sms;

use DateTimeInterface;

class SmsMessage
{
    public function __construct(
        public readonly string $destination,
        public readonly string $content,
        public readonly string $purpose,
        public readonly string $reference,
        public readonly ?DateTimeInterface $expiresAt = null,
    ) {
    }

    public function maskedDestination(): string
    {
        $digits = preg_replace('/\D+/', '', $this->destination) ?? '';
        $length = strlen($digits);
        if ($length < 7) {
            return str_repeat('*', max(4, $length));
        }

        return substr($digits, 0, 4).str_repeat('*', $length - 7).substr($digits, -3);
    }
}
