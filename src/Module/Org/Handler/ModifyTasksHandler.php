<?php

namespace App\Module\Org\Handler;

use App\Entity\Project\PackageTask;
use App\Entity\Project\Project;
use App\Repository\Project\PackageTaskRepository;
use App\Repository\Project\ProjectParticipantRepository;
use App\Repository\Project\WorkPackageRepository;
use App\Repository\Account\ProfileRepository;
use App\Repository\Common\StatusRepository;
use Doctrine\ORM\EntityManagerInterface;
use DateTimeImmutable;
use InvalidArgumentException;

class ModifyTasksHandler
{
    private const PRIORITIES = ['low', 'medium', 'high'];

    public function __construct(
        readonly private PackageTaskRepository $packageTaskRepository,
        readonly private ProjectParticipantRepository $projectParticipantRepository,
        readonly private WorkPackageRepository $workPackageRepository,
        readonly private ProfileRepository $profileRepository,
        readonly private StatusRepository $statusRepository,
        readonly private EntityManagerInterface $em
    ) {}

    public function handleModifyTask(array $taskData, Project $project): void
    {
        $task = $this->packageTaskRepository->find($taskData['id']);
        $workPackage = $this->workPackageRepository->find($taskData['workPackage']);
        $status = $this->statusRepository->find($taskData['status']);

        if (!$task || $task->getWorkPackage()?->getProject() !== $project) {
            throw new InvalidArgumentException('Invalid task or project id');
        }

        $assignedProfile = $this->validateTaskData($taskData, $workPackage, $status, $project, $task);

        $task->setTitle(trim((string) $taskData['title']))
            ->setDescription(trim((string) ($taskData['description'] ?? '')) ?: null)
            ->setAssignedProfile($assignedProfile)
            ->setWorkPackage($workPackage)
            ->setStatus($status)
            ->setPriority((string) $taskData['priority'])
            ->setSlug(trim((string) $taskData['slug']))
            ->setDueDate($this->parseDueDate($taskData['dueDate'] ?? ''));
        $this->em->flush();
    }

    public function createTask(array $taskData, Project $project): PackageTask
    {
        $workPackage = $this->workPackageRepository->find($taskData['workPackage']);
        $status = $this->statusRepository->find($taskData['status']);
        $assignedProfile = $this->validateTaskData($taskData, $workPackage, $status, $project);

        $task = new PackageTask();
        $task->setTitle(trim((string) $taskData['title']))
            ->setDescription(trim((string) ($taskData['description'] ?? '')) ?: null)
            ->setAssignedProfile($assignedProfile)
            ->setWorkPackage($workPackage)
            ->setStatus($status)
            ->setPriority((string) $taskData['priority'])
            ->setSlug(trim((string) $taskData['slug']))
            ->setDueDate($this->parseDueDate($taskData['dueDate'] ?? ''));

        $this->em->persist($task);
        $this->em->flush();

        return $task;
    }

    private function validateTaskData(array $taskData, mixed $workPackage, mixed $status, Project $project, ?PackageTask $currentTask = null): ?\App\Entity\Account\Profile
    {
        if (!$workPackage || $workPackage->getProject() !== $project || $workPackage->getDeletedAt() !== null) {
            throw new InvalidArgumentException('Invalid work package id');
        }

        if (!$status || $status->getScope() !== 'PackageTask') {
            throw new InvalidArgumentException('Invalid status id');
        }

        $title = trim((string) ($taskData['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 255) {
            throw new InvalidArgumentException('Taaktitel is verplicht en mag maximaal 255 tekens bevatten.');
        }

        $slug = trim((string) ($taskData['slug'] ?? ''));
        if ($slug === '' || mb_strlen($slug) > 255 || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            throw new InvalidArgumentException('Gebruik een geldige slug van maximaal 255 tekens (bijv. taak-uitvoering).');
        }

        $conflictingTask = $this->em->getRepository(PackageTask::class)->findOneBy([
            'workPackage' => $workPackage,
            'slug' => $slug,
        ]);
        if ($conflictingTask && $conflictingTask !== $currentTask) {
            throw new InvalidArgumentException('Deze slug bestaat al in het geselecteerde werkpakket.');
        }

        $description = trim((string) ($taskData['description'] ?? ''));
        if ($description === '') {
            throw new InvalidArgumentException('Taak-DoD is verplicht. Beschrijf het criterium voor afronding.');
        }

        $priority = (string) ($taskData['priority'] ?? 'low');
        if (!in_array($priority, self::PRIORITIES, true)) {
            throw new InvalidArgumentException('Invalid priority');
        }

        $assignedProfile = null;
        if (!empty($taskData['assignedProfile'])) {
            $assignedProfile = $this->profileRepository->find($taskData['assignedProfile']);
            if (!$assignedProfile) {
                throw new InvalidArgumentException('Invalid Profile id');
            }

            $participant = $this->projectParticipantRepository->findActiveParticipant($project, $assignedProfile);
            if (!$participant) {
                throw new InvalidArgumentException('Profile not in project');
            }
        }

        // Unieke slug per werkpakket
        if ($currentTask?->getSlug() !== $slug) {
            $existingTask = $this->packageTaskRepository->findOneBy(['slug' => $slug, 'workPackage' => $workPackage]);
            if ($existingTask) {
                throw new InvalidArgumentException('Deze slug is al in gebruik binnen het werkpakket.');
            }
        }

        return $assignedProfile;
    }

    private function parseDueDate(string $dueDate): ?DateTimeImmutable
    {
        if (trim($dueDate) === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($dueDate);
        } catch (\Exception) {
            throw new InvalidArgumentException('Ongeldige deadline.');
        }
    }

    public function deleteTaskById(string $taskId, Project $project): void
    {
        $task = $this->packageTaskRepository->find($taskId);

        if (!$task || $task->getWorkPackage()->getProject() !== $project) {
            throw new InvalidArgumentException('Invalid task or project id');
        }

        $task->softDelete();
        $this->em->flush();
    }

    public function restoreTaskById(string $taskId, Project $project): void
    {
        $task = $this->packageTaskRepository->find($taskId);

        if (!$task || $task->getWorkPackage()->getProject() !== $project) {
            throw new InvalidArgumentException('Invalid task or project id');
        }

        $task->restore();
        $this->em->flush();
    }

}
