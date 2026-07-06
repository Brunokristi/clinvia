<?php

namespace App\Modules\Calendar\Enums;

enum RecurrenceScope: string
{
    case This = 'this';
    case ThisAndFollowing = 'this_and_following';
    case Series = 'series';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
