<?php

namespace App\Enums;

enum ContactChangeStatus: string
{
    case None = 'none';
    case Detected = 'detected';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}
