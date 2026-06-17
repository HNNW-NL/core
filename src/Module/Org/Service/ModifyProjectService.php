<?php

namespace App\Module\Org\Service;

use App\Entity\Common\Status;
use App\Entity\Project\Project;
use App\Module\Org\DTO\ModifyProjectDTO;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class ModifyProjectService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public function modify(ModifyProjectDTO $dto): Project
    {
        $project = $this->findProject($dto->projectId);

        if ($project === null) {
            throw new \RuntimeException('Project not found');
        }

        if ($dto->organisationId === '') {
            throw new \RuntimeException('Project organisation is required');
        }

        if ($project->getOwnerOrganisation() === null) {
            throw new \RuntimeException('Project does not belong to an organisation');
        }

        $organisationId = (string) $project->getOwnerOrganisation()->getId();
        if ($organisationId !== $dto->organisationId) {
            throw new \RuntimeException('Project does not belong to the selected organisation');
        }

        if ($dto->hasStatusChanged()) {
            $project->setStatus($this->resolveStatus($dto->statusName));
        }

        $project = (new \App\Module\Org\Mapper\ModifyProjectMapper())->toEntity($project, $dto);

        if ($dto->hasStatusChanged()) {
            $statusName = $dto->statusName ?? '';
            if ($statusName === 'published' && $project->getPublishedAt() === null) {
                $project->publish();
            }

            if ($statusName === 'draft' || $statusName === 'archived') {
                $project->unpublish();
            }
        }

        $this->entityManager->flush();

        return $project;
    }

    private function findProject(string $projectId): ?Project
    {
        $projectId = trim($projectId);
        if ($projectId === '') {
            return null;
        }

        try {
            $uuid = Uuid::fromString($projectId);
        } catch (\Throwable) {
            return null;
        }

        $project = $this->entityManager->getRepository(Project::class)->find($uuid);

        return $project instanceof Project ? $project : null;
    }

    private function resolveStatus(?string $statusName): Status
    {
        $statusName = trim((string) $statusName);
        if ($statusName === '') {
            throw new \InvalidArgumentException('Status is required');
        }

        $status = $this->entityManager->getRepository(Status::class)->findOneBy([
            'name' => $statusName,
            'scope' => 'project',
        ]);

        if (!$status instanceof Status) {
            throw new \RuntimeException('Unknown project status: ' . $statusName);
        }

        return $status;
    }
}
