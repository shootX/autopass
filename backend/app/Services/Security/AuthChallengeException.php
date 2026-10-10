<?php

namespace App\Services\Security;

use RuntimeException;

class AuthChallengeException extends RuntimeException
{
    public function __construct(
        public readonly string $reason = 'invalid',
        public readonly int $status = 422
    ) {
        parent::__construct($reason);
    }
}
