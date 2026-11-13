<?php

namespace App\Module\AccountCentre\Enum;

enum AvailabilityType: string
{
    case STANDARD_CONTRACTUAL = 'standard_contractual';
    case FLEXIBLE_SHIFT = 'flexible_shift';
    case CASUAL_ZERO_HOURS = 'casual_zero_hours';
    case ON_CALL = 'on_call';
    case ON_SITE_STANDBY = 'on_site_standby';
    case EMERGENCY_INCIDENT = 'emergency_incident';
    case FULL = 'full';
    case RESTRICTED_ACCOMMODATED = 'restricted_accommodated';
    case PART_TIME = 'part_time';
}