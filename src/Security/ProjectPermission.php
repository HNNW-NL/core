<?php

namespace App\Security;

enum ProjectPermission: int
{
    case VIEW = 1 << 0;
    case EDIT = 1 << 1;
    case DELETE = 1 << 2;
    case INVITE = 1 << 3;

    public function in(?int $mask): bool
    {
        return (($mask ?? 0) & $this->value) === $this->value;
    }
}
