<?php

namespace App\Services\Security;

class TemporaryPassword
{
    public static function generate(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $length = 20;
        $bytes = random_bytes($length);
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $alphabet[ord($bytes[$i]) % strlen($alphabet)];
        }

        return $password;
    }
}
