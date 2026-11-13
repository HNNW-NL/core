<?php

namespace App\Module\Main\Service;

final class WorkPackageWorkflowService
{
    public const string STATUS_SCOPE = 'work_package';

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

    /** @return list<string> */
    public function getStatusNames(): array
    {
        return array_keys(self::TRANSITIONS);
    }

    public function getAllowedTransitions(string $currentStatus): array
    {
        return self::TRANSITIONS[$currentStatus] ?? [];
    }
}
