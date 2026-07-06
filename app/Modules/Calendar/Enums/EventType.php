<?php

namespace App\Modules\Calendar\Enums;

enum EventType: string
{
    case Booking = 'booking';
    case AvailabilityRule = 'availability_rule';
    case GroupEvent = 'group_event';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
