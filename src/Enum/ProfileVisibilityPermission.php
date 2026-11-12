<?php

namespace App\Enum;

enum ProfileVisibilityPermission: int
{
    case HEADLINE = 1 << 0;
    case EXPERIENCE = 1 << 1;
    case EDUCATION = 1 << 2;
    case CERTIFICATIONS = 1 << 3;
    case SKILLS = 1 << 4;
    case AVAILABILITY = 1 << 5;
    case SOCIAL_LINKS = 1 << 6;
    case LOCATION = 1 << 7;
}
