<?php

namespace App\Modules\Calendar\Enums;

enum EventStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Active = 'active';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
    case Deleted = 'deleted';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
