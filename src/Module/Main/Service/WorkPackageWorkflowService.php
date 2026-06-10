<?php

namespace App\Module\Main\Service;

final class WorkPackageWorkflowService
{
    private const TRANSITIONS = [
    'Draft' => ['Planned', 'Cancelled'],
    'Planned' => ['Open', 'Cancelled'],
    'Open' => ['Assigned', 'Cancelled'],
    'Assigned' => ['Closed', 'Cancelled'],
    'Closed' => [],
    'Cancelled' => [],
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