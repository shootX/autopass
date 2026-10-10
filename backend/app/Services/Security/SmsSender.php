<?php

namespace App\Services\Security;

interface SmsSender
{
    public function send(string $destination, string $content): void;
}
