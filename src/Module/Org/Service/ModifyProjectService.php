<?php

namespace App\Module\Org\Service;

use App\Entity\Common\Status;
use App\Entity\Project\Project;
use App\Module\Org\DTO\ModifyProjectDTO;
use Doctrine\ORM\EntityManagerInterface;

class ModifyProjectService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public function modify(ModifyProjectDTO $dto): Project
    {
        // Find the project
        $project = $this->entityManager
            ->getRepository(Project::class)
            ->find($dto->projectId);

        if (!$project) {
            throw new \RuntimeException('Project not found');
        }

        // Update status
        $project->setStatus($dto->status);

        // Handle publish logic
        if ($dto->shouldPublishNow()) {
            $project->setPublishedAt(new \DateTimeImmutable());
            $project->setPublishedBy($dto->publishedBy);

            if ($dto->publishMessage) {
                $project->setPublishMessage($dto->publishMessage);
            }
        }

        // Handle schedule logic
        if ($dto->shouldSchedule()) {
            $project->setScheduledFor($dto->scheduledFor);
            $project->setPublishedAt(null);
        }

        // Handle draft
        if ($dto->status === Status::DRAFT) {
            $project->setPublishedAt(null);
            $project->setScheduledFor(null);
            $project->setPublishedBy(null);
        }

        $this->entityManager->flush();

        return $project;
    }
}
