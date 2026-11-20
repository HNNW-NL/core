<?php

namespace App\Security;

use App\Entity\Project\Project;
use App\Entity\Project\ProjectRole;

enum ProjectPermission: int
{
    case VIEW = 1 << 0;
    case EDIT = 1 << 1;
    case DELETE = 1 << 2;
    case INVITE = 1 << 3;

    public function isGrantedBy(?ProjectRole $role, Project $project): bool
    {
        return $role?->getProject() === $project && $this->in($role->getPermissionsMask());
    }

    public function in(?int $mask): bool
    {
        return (($mask ?? 0) & $this->value) === $this->value;
    }
}
