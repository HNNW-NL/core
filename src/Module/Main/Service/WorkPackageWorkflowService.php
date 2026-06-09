<?php

namespace App\Module\Main\Service;

final class WorkPackageWorkflowService
{
    private const TRANSITIONS = [
        'Draft' => ['Planned'],
        'Planned' => ['Open'],
        'Open' => ['Assigned'],
        'Assigned' => ['Closed', 'Open'],
        'Closed' => [],
    ];

    public function canTransition(string $currentStatus, string $newStatus): bool
    {
        return in_array(
            $newStatus,
            self::TRANSITIONS[$currentStatus] ?? [],
            true
        );
    }

    public function getAllowedTransitions(string $currentStatus): array
    {
        return self::TRANSITIONS[$currentStatus] ?? [];
    }
}