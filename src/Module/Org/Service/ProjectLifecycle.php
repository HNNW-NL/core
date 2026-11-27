<?php

namespace App\Module\Org\Service;

use App\Entity\Common\Status;
use App\Entity\Project\Project;
use App\Entity\Project\PackageTask;
use App\Entity\Project\ProjectApplication;
use Doctrine\ORM\EntityManagerInterface;

final class ProjectLifecycle
{
    private const OPEN_APPLICATION_STATUSES = ['active', 'published'];
    public const PUBLIC_STATUSES = [...self::OPEN_APPLICATION_STATUSES, 'applications_closed', 'completed'];

    private const TRANSITIONS = [
        'draft' => ['published', 'archived'],
        'published' => ['applications_closed'],
        'applications_closed' => ['completed'],
        'completed' => ['archived'],
    ];

    public function __construct(private readonly EntityManagerInterface $entityManager,
        private readonly ProjectLeadEligibility $leadEligibility,
    ) {}

    public function allowedTargets(Project $project): array
    {
        $status = strtolower((string) $project->getStatus()?->getName());
        $targets = match ($status) {
            'active' => $project->getPublishedAt() === null ? ['published'] : ['applications_closed'],
            default => self::TRANSITIONS[$status] ?? [],
        };

        if ($targets === []) {
            return [];
        }

        return array_values(array_filter($targets, fn (string $target): bool => $this->transitionStatus($target) !== null));
    }

    public function initialStatus(): Status
    {
        return $this->findStatus('draft') ?? $this->findStatus('active')
            ?? throw new \InvalidArgumentException('Configure the draft or active project status before creating projects.');
    }

    public static function isPubliclyVisible(Project $project): bool
    {
        return $project->getVisibility() === 'public'
            && $project->getPublishedAt() !== null
            && in_array(strtolower((string) $project->getStatus()?->getName()), self::PUBLIC_STATUSES, true);
    }

    public static function acceptsApplications(Project $project): bool
    {
        return $project->getPublishedAt() !== null
            && in_array(strtolower((string) $project->getStatus()?->getName()), self::OPEN_APPLICATION_STATUSES, true);
    }

    public static function allowsTaskChanges(Project $project): bool
    {
        return $project->getDeletedAt() === null
            && !in_array(strtolower((string) $project->getStatus()?->getName()), ['completed', 'archived'], true);
    }

    public static function assertTaskChangesAllowed(Project $project): void
    {
        if (!self::allowsTaskChanges($project)) {
            throw new \InvalidArgumentException('Tasks cannot be changed in a completed, archived or deleted project.');
        }
    }

    private function findStatus(string $name): ?Status
    {
        return $this->entityManager->getRepository(Status::class)
            ->findOneBy(['scope' => 'project', 'name' => $name]);
    }

    private function transitionStatus(string $target): ?Status
    {
        // Legacy installations publish an active project by setting its publication timestamp.
        return $this->findStatus($target) ?? ($target === 'published' ? $this->findStatus('active') : null);
    }

    public function transition(Project $project, string $next): void
    {
        if (!in_array($next, $this->allowedTargets($project), true)) {
            throw new \InvalidArgumentException('This status change is no longer available.');
        }

        $status = $this->transitionStatus($next)
            ?? throw new \InvalidArgumentException('This status change is no longer configured.');
        if ($next === 'published') {
            if (trim((string) $project->getTitle()) === '' || trim((string) $project->getSummary()) === ''
                || trim((string) $project->getDescription()) === '' || $project->getOwnerAccount() === null) {
                throw new \InvalidArgumentException('Add a title, summary, description and project lead before publishing.');
            }
            $organisation = $project->getOwnerOrganisation();
            $lead = $project->getOwnerAccount();
            if ($organisation === null || $organisation->getDeletedAt() !== null
                || !isset($this->leadEligibility->projectLeads($organisation)[(string) $lead->getId()])) {
                throw new \InvalidArgumentException('Choose an eligible project lead from this organisation before publishing.');
            }
            $project->publish();
        }

        if ($next === 'completed') {
            $this->assertCanComplete($project);
        }

        $project->setStatus($status);
        if ($next === 'archived') {
            $project->unpublish();
        }
    }

    private function assertCanComplete(Project $project): void
    {
        $openTasks = $this->entityManager->createQueryBuilder()
            ->select('COUNT(task.id)')
            ->from(PackageTask::class, 'task')
            ->join('task.workPackage', 'package')
            ->leftJoin('task.status', 'taskStatus')
            ->where('package.project = :project AND package.deletedAt IS NULL AND task.deletedAt IS NULL')
            ->andWhere('taskStatus.id IS NULL OR LOWER(taskStatus.name) NOT IN (:finished)')
            ->setParameter('project', $project)
            ->setParameter('finished', ['completed', 'done', 'cancelled', 'canceled'])
            ->getQuery()
            ->getSingleScalarResult();
        if ((int) $openTasks > 0) {
            throw new \InvalidArgumentException('Finish or cancel all open tasks before completing the project.');
        }

        $pendingApplications = $this->entityManager->createQueryBuilder()
            ->select('COUNT(application.id)')
            ->from(ProjectApplication::class, 'application')
            ->join('application.status', 'applicationStatus')
            ->where('application.project = :project AND application.deletedAt IS NULL')
            ->andWhere('LOWER(applicationStatus.name) IN (:pending)')
            ->setParameter('project', $project)
            ->setParameter('pending', ['send', 'submitted'])
            ->getQuery()
            ->getSingleScalarResult();
        if ((int) $pendingApplications > 0) {
            throw new \InvalidArgumentException('Review all submitted applications before completing the project.');
        }
    }
}
