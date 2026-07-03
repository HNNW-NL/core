<?php

namespace App\Module\Org\Mapper;

use App\Entity\Project\WorkPackage;

class WorkPackageMapper
{
    public function toArray(WorkPackage $workPackage): array
    {
        $tasks = $this->getTasks($workPackage);
        $mappedTasks = $this->mapTasks($tasks);
        $taskCount = count($mappedTasks);
        $completedTaskCount = $this->countCompletedTasks($mappedTasks);
        $statusName = $this->getStatusName($workPackage);

        return [
            'id' => $workPackage->getId()->toRfc4122(),
            'title' => $workPackage->getTitle() ?? 'Zonder titel',
            'slug' => $workPackage->getSlug(),
            'description' => $workPackage->getDescription(),
            'dueDate' => $workPackage->getDueDate()?->format('d-m-Y'),
            'dueDateInput' => $workPackage->getDueDate()?->format('Y-m-d'),
            'status' => $statusName,
            'statusClass' => $this->getStatusClass($statusName),
            'taskCount' => $taskCount,
            'completedTaskCount' => $completedTaskCount,
            'progress' => $taskCount > 0 ? (int) round(($completedTaskCount / $taskCount) * 100) : 0,
            'tasks' => $mappedTasks,
        ];
    }

    public function manyToArray(array $workPackages): array
    {
        return array_map(fn (WorkPackage $workPackage) => $this->toArray($workPackage), $workPackages);
    }

    private function getStatusName(WorkPackage $workPackage): string
    {
        $status = $workPackage->getStatus();

        if ($status && method_exists($status, 'getName')) {
            return $status->getName();
        }

        return 'Open';
    }

    private function getStatusClass(string $statusName): string
    {
        return match (strtolower($statusName)) {
            'bezig', 'in progress', 'in_progress' => 'busy',
            'gereed', 'done', 'completed', 'complete' => 'done',
            default => 'open',
        };
    }

    private function getTasks(WorkPackage $workPackage): array
    {
        if (method_exists($workPackage, 'getWorkPackageTasks')) {
            return $workPackage->getWorkPackageTasks()->toArray();
        }

        if (method_exists($workPackage, 'getPackageTasks')) {
            return $workPackage->getPackageTasks()->toArray();
        }

        if (method_exists($workPackage, 'getTasks')) {
            return $workPackage->getTasks()->toArray();
        }

        return [];
    }

    private function mapTasks(array $tasks): array
    {
        $mappedTasks = [];

        foreach ($tasks as $task) {
            if (method_exists($task, 'getDeletedAt') && $task->getDeletedAt() !== null) {
                continue;
            }

            $status = method_exists($task, 'getStatus') ? $task->getStatus() : null;
            $statusName = $status && method_exists($status, 'getName') ? $status->getName() : 'Open';
            $assignedProfile = method_exists($task, 'getAssignedProfile') ? $task->getAssignedProfile() : null;
            $assignedName = $this->getAssignedName($assignedProfile);

            $mappedTasks[] = [
                'id' => $task->getId()->toRfc4122(),
                'title' => method_exists($task, 'getTitle') ? $task->getTitle() : 'Zonder titel',
                'description' => method_exists($task, 'getDescription') ? $task->getDescription() : null,
                'dueDate' => method_exists($task, 'getDueDate') ? $task->getDueDate()?->format('d-m-Y') : null,
                'priority' => method_exists($task, 'getPriority') ? $task->getPriority() : null,
                'status' => $statusName,
                'statusClass' => $this->getTaskStatusClass($statusName),
                'assignedName' => $assignedName,
                'assignedInitials' => $this->getInitials($assignedName),
                'isCompleted' => $this->isCompletedStatus($statusName),
            ];
        }

        return $mappedTasks;
    }

    private function countCompletedTasks(array $tasks): int
    {
        $completed = 0;

        foreach ($tasks as $task) {
            if (($task['isCompleted'] ?? false) === true) {
                $completed++;
            }
        }

        return $completed;
    }

    private function getTaskStatusClass(string $statusName): string
    {
        return match (strtolower($statusName)) {
            'bezig', 'in progress', 'in_progress' => 'busy',
            'gereed', 'done', 'completed', 'complete' => 'done',
            default => 'open',
        };
    }

    private function isCompletedStatus(string $statusName): bool
    {
        return in_array(strtolower($statusName), ['gereed', 'done', 'completed', 'complete'], true);
    }

    private function getAssignedName(?object $profile): ?string
    {
        if (!$profile) {
            return null;
        }

        if (method_exists($profile, 'getDisplayName') && $profile->getDisplayName()) {
            return $profile->getDisplayName();
        }

        $firstName = method_exists($profile, 'getFirstName') ? $profile->getFirstName() : null;
        $lastName = method_exists($profile, 'getLastName') ? $profile->getLastName() : null;
        $fullName = trim(sprintf('%s %s', $firstName, $lastName));

        return $fullName !== '' ? $fullName : null;
    }

    private function getInitials(?string $name): ?string
    {
        if (!$name) {
            return null;
        }

        $parts = array_values(array_filter(explode(' ', $name)));

        if (!$parts) {
            return null;
        }

        $first = mb_substr($parts[0], 0, 1);
        $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';

        return mb_strtoupper($first . $last);
    }
}
