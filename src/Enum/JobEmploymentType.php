<?php

namespace App\Enum;

enum JobEmploymentType: string
{
    case EMPLOYMENT = 'employment';
    case INTERNSHIP = 'internship';
    case TRAINEESHIP = 'traineeship';
    case FREELANCE = 'freelance';
    case VOLUNTEER = 'volunteer';
    case OTHER = 'other';
}
