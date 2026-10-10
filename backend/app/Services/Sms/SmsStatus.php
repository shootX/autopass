<?php

namespace App\Services\Sms;

enum SmsStatus: string
{
    case Simulated = 'simulated';
    case Accepted = 'accepted';
    case Failed = 'failed';
    case Unknown = 'unknown';
}
