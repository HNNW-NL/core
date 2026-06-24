<?php

namespace App\Module\Org\Service;

use App\Entity\Common\Status;
use App\Entity\Project\Project;
use App\Module\Org\DTO\ModifyProjectDTO;
use App\Module\Org\Mapper\ModifyProjectMapper;
use Doctrine\ORM\EntityManagerInterface;

class ModifyProjectService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ModifyProjectMapper $mapper,
    ) {}

    public function modify(ModifyProjectDTO $dto): Project
    {
        $project = $this->findProject($dto->projectId);

        if ($project === null) {
            throw new \RuntimeException('Project not found');
        }

        if ($project->getOwnerOrganisation() === null) {
            throw new \RuntimeException('Project does not belong to an organisation');
        }

        $organisationId = (string) $project->getOwnerOrganisation()->getId();
        if ($organisationId !== $dto->organisationId) {
            throw new \RuntimeException('Project does not belong to the selected organisation');
        }

        if ($dto->hasStatusNameChanged()) {
            $project->setStatus($this->resolveStatus($dto->statusName));
        }

        $project = $this->mapper->toEntity($project, $dto);

        if ($dto->hasStatusNameChanged()) {
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

    public function delete(string $projectId, string $organisationId): Project
    {
        $project = $this->findProject($projectId);

        if ($project === null) {
            throw new \RuntimeException('Project not found');
        }

        if ($project->getOwnerOrganisation() === null) {
            throw new \RuntimeException('Project does not belong to an organisation');
        }

        $projectOrganisationId = (string) $project->getOwnerOrganisation()->getId();
        if ($projectOrganisationId !== $organisationId) {
            throw new \RuntimeException('Project does not belong to the selected organisation');
        }

        if ($project->getDeletedAt() !== null) {
            throw new \RuntimeException('Project is already deleted');
        }

        $project->softDelete();
        $this->entityManager->flush();

        return $project;
    }

    private function findProject(string $projectId): ?Project
    {
        $projectId = trim($projectId);
        if ($projectId === '') {
            return null;
        }

        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $projectId)) {
            return null;
        }

        $project = $this->entityManager->getRepository(Project::class)->find($projectId);

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
