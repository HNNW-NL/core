<?php

namespace App\Module\Org\Service;

use App\Entity\Account\Account;
use App\Entity\Project\Project;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class ProjectManagementActions
{
    public function __construct(
        private ProjectWriteLock $lock,
        private ProjectManagementAccess $access,
        private ProjectLifecycle $lifecycle,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    public function delete(Project $project, string $confirmation): void
    {
        $this->lock->run($project, function () use ($project, $confirmation): void {
            if (!$this->access->canDelete($project)) {
                throw new AccessDeniedHttpException();
            }
            if ($confirmation !== 'DELETE') {
                throw new \InvalidArgumentException('Type DELETE to confirm project deletion.');
            }

            $project->softDelete();
            $project->unpublish();
            $this->entityManager->flush();
        });
    }

    public function transition(Project $project, string $target, Account $actor): void
    {
        $previous = $this->lock->run($project, function () use ($project, $target): ?string {
            if (!$this->access->canEdit($project)) {
                throw new AccessDeniedHttpException();
            }

            $previous = $project->getStatus()?->getName();
            $this->lifecycle->transition($project, $target);
            $this->entityManager->flush();

            return $previous;
        });

        $this->logger->info('Project status changed', [
            'project_id' => (string) $project->getId(),
            'from' => $previous,
            'to' => $target,
            'account_id' => (string) $actor->getId(),
        ]);
    }
}
