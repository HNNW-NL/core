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

        // Update optional fields if provided
        if ($dto->hasDescriptionChanged()) {
            $project->setDescription($dto->description);
        }

        if ($dto->hasSummaryChanged()) {
            $project->setSummary($dto->summary);
        }

        // Handle modify now
        if ($dto->shouldModifyNow()) {
            $project->setModifiedAt(new \DateTimeImmutable());
            $project->setModifiedBy($dto->modifiedBy);

            if ($dto->hasModifyMessage()) {
                $project->setModifyMessage($dto->modifyMessage);
            }
        }

        // Handle schedule logic
        if ($dto->shouldSchedule()) {
            $project->setScheduledFor($dto->scheduledFor);
            $project->setModifiedAt(null);
        }

        // Handle draft — clear modification data
        if ($dto->status === Status::DRAFT) {
            $project->setModifiedAt(null);
            $project->setScheduledFor(null);
            $project->setModifiedBy(null);
        }

        // Always bump the timestamp
        $project->setTimeModified(new \DateTimeImmutable());

        $this->entityManager->flush();

        return $project;
    }
}
