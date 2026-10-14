<?php

namespace App\Enums;

enum AppointmentStatus: int
{
    case Pending = 0;
    case Confirmed = 1;
    case Cancelled = 2;
    case Completed = 3;
    case Expired = 4;
    case Rescheduled = 5;

    public static function active(): array
    {
        return [self::Pending->value, self::Confirmed->value, self::Rescheduled->value];
    }

    public function terminal(): bool
    {
        return in_array($this, [self::Cancelled, self::Completed, self::Expired], true);
    }
}
