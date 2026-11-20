<?php

namespace App\Module\Org\Service;

use App\Entity\Common\Status;
use App\Entity\Project\Project;
use Doctrine\ORM\EntityManagerInterface;

final class ProjectLifecycle
{
    private const TRANSITIONS = [
        'draft' => ['published', 'archived'],
        'active' => ['published', 'applications_closed'],
        'published' => ['applications_closed'],
        'applications_closed' => ['completed'],
        'completed' => ['archived'],
    ];

    public function __construct(private readonly EntityManagerInterface $entityManager) {}

    public function allowedTargets(Project $project): array
    {
        return self::TRANSITIONS[strtolower((string) $project->getStatus()?->getName())] ?? [];
    }

    public function status(string $name): Status
    {
        $status = $this->entityManager->getRepository(Status::class)->findOneBy(['scope' => 'project', 'name' => $name]);
        if (!$status instanceof Status) {
            throw new \LogicException(sprintf('The project status "%s" is missing.', $name));
        }

        return $status;
    }

    public function transition(Project $project, string $next): void
    {
        if (!in_array($next, $this->allowedTargets($project), true)) {
            throw new \InvalidArgumentException('This status change is no longer available.');
        }

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

        $project->setStatus($this->status($next));
        if ($next === 'archived') {
            $project->unpublish();
        }
    }
}
