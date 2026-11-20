<?php

namespace App\Module\Org\Service;

use App\Entity\Common\Status;
use App\Entity\Project\Project;
use Doctrine\ORM\EntityManagerInterface;

final class ProjectLifecycle
{
    private const TRANSITIONS = [
        'draft' => ['published', 'archived'],
        'published' => ['applications_closed'],
        'applications_closed' => ['completed'],
        'completed' => ['archived'],
    ];

    public function __construct(private readonly EntityManagerInterface $entityManager) {}

    public function allowedTargets(Project $project): array
    {
        $status = strtolower((string) $project->getStatus()?->getName());
        if ($status === 'active') {
            // The existing active status can represent either state. Publication is recorded separately.
            $status = $project->getPublishedAt() === null ? 'draft' : 'published';
        }

        return self::TRANSITIONS[$status] ?? [];
    }

    public function status(string $name): Status
    {
        $repository = $this->entityManager->getRepository(Status::class);
        $status = $repository->findOneBy(['scope' => 'project', 'name' => $name]);
        if ($status === null && in_array($name, ['draft', 'published'], true)) {
            $status = $repository->findOneBy(['scope' => 'project', 'name' => 'active']);
        }
        if (!$status instanceof Status) {
            throw new \InvalidArgumentException(sprintf('This action is unavailable until the project status "%s" is configured.', $name));
        }

        return $status;
    }

    public function transition(Project $project, string $next): void
    {
        if (!in_array($next, $this->allowedTargets($project), true)) {
            throw new \InvalidArgumentException('This status change is no longer available.');
        }

        $status = $this->status($next);
        if ($next === 'published') {
            if (trim((string) $project->getTitle()) === '' || trim((string) $project->getSummary()) === ''
                || trim((string) $project->getDescription()) === '' || $project->getOwnerAccount() === null) {
                throw new \InvalidArgumentException('Add a title, summary, description and project lead before publishing.');
            }
            $project->publish();
        }

        if ($next === 'completed') {
            foreach ($project->getWorkPackages() as $package) {
                foreach ($package->getWorkPackageTasks() as $task) {
                    if ($task->getDeletedAt() === null
                        && !in_array(strtolower((string) $task->getStatus()?->getName()), ['completed', 'done', 'cancelled', 'canceled'], true)) {
                        throw new \InvalidArgumentException('Finish or cancel all open tasks before completing the project.');
                    }
                }
            }
            foreach ($project->getApplications() as $application) {
                if ($application->getDeletedAt() === null
                    && in_array(strtolower((string) $application->getStatus()?->getName()), ['send', 'submitted'], true)) {
                    throw new \InvalidArgumentException('Review all submitted applications before completing the project.');
                }
            }
        }

        $project->setStatus($status);
        if ($next === 'archived') {
            $project->unpublish();
        }
    }
}
