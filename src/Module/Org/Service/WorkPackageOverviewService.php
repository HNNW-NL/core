<?php

namespace App\Module\Org\Service;

use App\Entity\Project\Project;
use App\Repository\Project\WorkPackageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class WorkPackageOverviewService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly WorkPackageRepository $workPackageRepository,
    ) {
    }

    public function getWorkPackagesForProjectId(string $projectId): array
    {
        if (!Uuid::isValid($projectId)) {
            return [];
        }

        $project = $this->entityManager->find(Project::class, Uuid::fromString($projectId));

        if (!$project) {
            return [];
        }

        return $this->workPackageRepository->findActiveByProject($project);
    }
}