<?php

namespace App\Security;

use App\Entity\Project\ProjectParticipant;

final class ProjectParticipation
{
    public const SCOPE = 'projectparticipant';
    public const ACTIVE_NAMES = ['active', 'actief'];

    public static function isActive(ProjectParticipant $participant): bool
    {
        return $participant->getJoinedAt() !== null && $participant->getLeftAt() === null
            && strtolower((string) $participant->getStatus()?->getScope()) === self::SCOPE
            && in_array(strtolower((string) $participant->getStatus()?->getName()), self::ACTIVE_NAMES, true);
    }
}
