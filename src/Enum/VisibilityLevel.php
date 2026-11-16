<?php

namespace App\Enum;

enum VisibilityLevel: string
{
    case PRIVATE = 'private';
    case CONNECTIONS = 'connections';
    case PUBLIC = 'public';
}
