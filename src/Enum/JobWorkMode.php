<?php

namespace App\Enum;

enum JobWorkMode: string
{
    case ONSITE = 'onsite';
    case HYBRID = 'hybrid';
    case REMOTE = 'remote';
}
