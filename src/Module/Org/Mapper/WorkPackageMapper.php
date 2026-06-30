<?php

namespace App\Module\Org\Mapper;

use App\Entity\Project\WorkPackage;

class WorkPackageMapper
{
    public function toArray(WorkPackage $workPackage): array
    {
        $tasks = $this->getTasks($workPackage);
        $taskCount = count($tasks);
        $completedTaskCount = $this->countCompletedTasks($tasks);
        $statusName = $this->getStatusName($workPackage);

        return [
            'id' => $workPackage->getId()->toRfc4122(),
            'title' => $workPackage->getTitle() ?? 'Zonder titel',
            'description' => $workPackage->getDescription(),
            'dueDate' => $workPackage->getDueDate()?->format('d-m-Y'),
            'status' => $statusName,
            'statusClass' => $this->getStatusClass($statusName),
            'taskCount' => $taskCount,
            'completedTaskCount' => $completedTaskCount,
            'progress' => $taskCount > 0 ? (int) round(($completedTaskCount / $taskCount) * 100) : 0,
            'tasks' => $this->mapTasks($tasks),
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

        if (method_exists($workPackage, 'getTasks')) {
            return $workPackage->getTasks()->toArray();
        }

        return [];
    }

    private function countCompletedTasks(array $tasks): int
    {
        $completed = 0;

        foreach ($tasks as $task) {
            $status = method_exists($task, 'getStatus') ? $task->getStatus() : null;
            $statusName = $status && method_exists($status, 'getName') ? strtolower($status->getName()) : '';

            if (in_array($statusName, ['gereed', 'done', 'completed', 'complete'], true)) {
                $completed++;
            }
        }

        return $completed;
    }

    private function mapTasks(array $tasks): array
    {
        return array_map(function ($task): array {
            $status = method_exists($task, 'getStatus') ? $task->getStatus() : null;
            $statusName = $status && method_exists($status, 'getName') ? $status->getName() : 'Open';

            return [
                'title' => method_exists($task, 'getTitle') ? $task->getTitle() : 'Zonder titel',
                'description' => method_exists($task, 'getDescription') ? $task->getDescription() : null,
                'status' => $statusName,
                'statusClass' => $this->getStatusClass($statusName),
                'isCompleted' => in_array(strtolower($statusName), ['gereed', 'done', 'completed', 'complete'], true),
                'assignedName' => $this->getAssignedName($task),
                'assignedInitials' => $this->getAssignedInitials($task),
            ];
        }, $tasks);
    }

    private function getAssignedName(object $task): ?string
    {
        if (!method_exists($task, 'getAssignedProfile') || !$task->getAssignedProfile()) {
            return null;
        }

        $profile = $task->getAssignedProfile();

        if (method_exists($profile, 'getDisplayName') && $profile->getDisplayName()) {
            return $profile->getDisplayName();
        }

        $firstName = method_exists($profile, 'getFirstName') ? $profile->getFirstName() : '';
        $lastName = method_exists($profile, 'getLastName') ? $profile->getLastName() : '';

        $name = trim($firstName . ' ' . $lastName);

        return $name !== '' ? $name : null;
    }

    private function getAssignedInitials(object $task): ?string
    {
        $name = $this->getAssignedName($task);

        if (!$name) {
            return null;
        }

        return strtoupper(substr($name, 0, 1));
    }
}