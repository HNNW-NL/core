<?php

namespace App\Workflow;

use App\Entity\Common\Status;

final class ProjectApplicationWorkflow
{
    public const APPLICATION_SCOPE = 'ProjectApplication';
    public const REJECT_REASON_MAX_LENGTH = 500;
    public const PENDING_NAMES = ['send', 'sent', 'pending', 'submitted', 'ingediend'];
    public const ACCEPTED_NAMES = ['accepted', 'geaccepteerd'];
    public const REJECTED_NAMES = ['rejected', 'afgewezen'];

    public static function matches(?Status $status, array $names): bool
    {
        return strcasecmp((string) $status?->getScope(), self::APPLICATION_SCOPE) === 0
            && in_array(mb_strtolower((string) $status?->getName()), $names, true);
    }
}
