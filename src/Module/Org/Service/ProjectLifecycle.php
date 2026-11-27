<?php

namespace App\Module\Org\Service;

use App\Entity\Common\Status;
use App\Entity\Project\Project;
use App\Entity\Project\PackageTask;
use App\Entity\Project\ProjectApplication;
use App\Entity\Project\ProjectParticipant;
use App\Repository\Project\ProjectParticipantRepository;
use App\Workflow\ProjectApplicationWorkflow;
use Doctrine\ORM\EntityManagerInterface;

final class ProjectLifecycle
{
    private const OPEN_APPLICATION_STATUSES = ['active', 'published'];
    public const PUBLIC_STATUSES = [...self::OPEN_APPLICATION_STATUSES, 'applications_closed', 'completed'];

    private const TRANSITIONS = [
        'draft' => ['published', 'archived'],
        'published' => ['applications_closed', 'withdrawn'],
        'applications_closed' => ['completed', 'withdrawn'],
        'completed' => ['archived'],
    ];

    public function __construct(private readonly EntityManagerInterface $entityManager,
        private readonly ProjectLeadEligibility $leadEligibility,
    ) {}

    public function allowedTargets(Project $project): array
    {
        return array_values(array_filter(
            $this->candidateTargets($project),
            fn (string $target): bool => $this->transitionStatus($target) !== null,
        ));
    }

    public function unavailableTargets(Project $project): array
    {
        return array_values(array_filter(
            $this->candidateTargets($project),
            fn (string $target): bool => $this->transitionStatus($target) === null,
        ));
    }

    private function candidateTargets(Project $project): array
    {
        if (!self::hasValidProjectContext($project)) {
            return [];
        }

        $status = strtolower((string) $project->getStatus()?->getName());
        return match ($status) {
            'active' => $project->getPublishedAt() === null ? ['published'] : ['applications_closed', 'withdrawn'],
            default => self::TRANSITIONS[$status] ?? [],
        };
    }

    public function initialStatus(): Status
    {
        return $this->findStatus('draft') ?? $this->findStatus('active')
            ?? throw new \InvalidArgumentException('Configure the draft or active project status before creating projects.');
    }

    public static function isPubliclyVisible(Project $project): bool
    {
        return self::hasValidProjectContext($project)
            && $project->getVisibility() === 'public'
            && $project->getPublishedAt() !== null
            && in_array(strtolower((string) $project->getStatus()?->getName()), self::PUBLIC_STATUSES, true);
    }

    public function acceptsApplications(Project $project): bool
    {
        return self::isPubliclyVisible($project)
            && in_array(strtolower((string) $project->getStatus()?->getName()), self::OPEN_APPLICATION_STATUSES, true)
            && ($project->getEndDate() === null || $project->getEndDate() >= new \DateTimeImmutable())
            && $this->hasParticipantCapacity($project);
    }

    private function hasParticipantCapacity(Project $project): bool
    {
        $capacity = $project->getCapacity();
        if ($capacity === null) {
            return true;
        }

        $query = $this->entityManager->createQueryBuilder()
            ->select('COUNT(DISTINCT participantProfile.id)')
            ->from(ProjectParticipant::class, 'participant')
            ->where('participant.project = :project')
            ->setParameter('project', $project);

        return (int) ProjectParticipantRepository::restrictToActive($query, 'participant')
            ->getQuery()
            ->getSingleScalarResult() < $capacity;
    }

    public static function allowsApplicationReview(Project $project): bool
    {
        return self::allowsTaskChanges($project);
    }

    public static function allowsTaskChanges(Project $project): bool
    {
        return self::hasValidProjectContext($project)
            && in_array(strtolower((string) $project->getStatus()?->getName()), ['active', 'draft', 'published', 'applications_closed'], true);
    }

    private static function hasValidProjectContext(Project $project): bool
    {
        $organisation = $project->getOwnerOrganisation();

        return $project->getDeletedAt() === null
            && $organisation !== null
            && $organisation->getDeletedAt() === null
            && $project->getStatus()?->getScope() === 'project';
    }

    public static function assertTaskChangesAllowed(Project $project): void
    {
        if (!self::allowsTaskChanges($project)) {
            throw new \InvalidArgumentException('The current project state does not allow this change.');
        }
    }

    private function findStatus(string $name): ?Status
    {
        return $this->entityManager->getRepository(Status::class)
            ->findOneBy(['scope' => 'project', 'name' => $name]);
    }

    private function transitionStatus(string $target): ?Status
    {
        if ($target === 'withdrawn') {
            return $this->findStatus('draft') ?? $this->findStatus('active');
        }

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
        if (in_array($next, ['archived', 'withdrawn'], true)) {
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
            ->andWhere('taskStatus.id IS NULL OR taskStatus.scope IS NULL OR LOWER(taskStatus.scope) NOT IN (:taskScopes) OR taskStatus.name IS NULL OR LOWER(taskStatus.name) NOT IN (:finished)')
            ->setParameter('project', $project)
            ->setParameter('taskScopes', ['packagetask', 'package_task'])
            ->setParameter('finished', ['completed', 'done', 'cancelled', 'canceled'])
            ->getQuery()
            ->getSingleScalarResult();
        if ((int) $openTasks > 0) {
            throw new \InvalidArgumentException('Finish or cancel all open tasks before completing the project.');
        }

        $pendingApplications = $this->entityManager->createQueryBuilder()
            ->select('COUNT(application.id)')
            ->from(ProjectApplication::class, 'application')
            ->leftJoin('application.status', 'applicationStatus')
            ->where('application.project = :project AND application.deletedAt IS NULL')
            ->andWhere('applicationStatus.id IS NULL OR applicationStatus.scope IS NULL OR LOWER(applicationStatus.scope) <> :applicationScope OR applicationStatus.name IS NULL OR LOWER(applicationStatus.name) NOT IN (:reviewed)')
            ->setParameter('project', $project)
            ->setParameter('applicationScope', strtolower(ProjectApplicationWorkflow::APPLICATION_SCOPE))
            ->setParameter('reviewed', [...ProjectApplicationWorkflow::ACCEPTED_NAMES, ...ProjectApplicationWorkflow::REJECTED_NAMES])
            ->getQuery()
            ->getSingleScalarResult();
        if ((int) $pendingApplications > 0) {
            throw new \InvalidArgumentException('Review all submitted applications before completing the project.');
        }
    }
}
