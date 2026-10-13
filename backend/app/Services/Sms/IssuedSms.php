<?php

namespace App\Services\Sms;

class IssuedSms
{
    public function __construct(
        public readonly string $publicId,
        public readonly SmsResult $result,
    ) {
    }
}
