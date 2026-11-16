<?php

namespace App\Enum;

enum AvailabilityType: string
{
    case AVAILABLE = 'available';
    case UNAVAILABLE = 'unavailable';
    case PREFERRED = 'preferred';
}
