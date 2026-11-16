<?php

namespace App\Module\Org\Service;

use App\Entity\Project\Project;
use App\Repository\Project\WorkPackageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

class WorkPackageOverviewService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly WorkPackageRepository $workPackageRepository,
    ) {
    }

    public function getWorkPackagesForProjectId(string $projectId): array
    {
        if (!Ulid::isValid($projectId)) {
            return [];
        }

        $project = $this->entityManager->find(Project::class, Ulid::fromString($projectId));

        if (!$project) {
            return [];
        }

        return $this->workPackageRepository->findActiveByProject($project);
    }
}
