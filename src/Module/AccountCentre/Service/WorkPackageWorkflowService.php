<?php

namespace App\Module\Main\Service;

final class WorkPackageWorkflowService
{
    private const TRANSITIONS = [
        'Draft' => ['Open', 'Cancelled'],
        'Open' => ['In Progress', 'Cancelled'],
        'In Progress' => ['Review', 'Cancelled'],
        'Review' => ['Completed', 'In Progress'],
        'Completed' => [],
        'Cancelled' => [],
    ];

    public function canTransition(
        string $currentStatus,
        string $newStatus
    ): bool {
        return in_array(
            $newStatus,
            self::TRANSITIONS[$currentStatus] ?? [],
            true
        );
    }

    public function getAllowedTransitions(
        string $currentStatus
    ): array {
        return self::TRANSITIONS[$currentStatus] ?? [];
    }
}