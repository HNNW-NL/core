<?php

namespace App\Module\Org\Service;

use App\Module\Org\DTO\CreateProjectDTO;
use App\Entity\Project\Project;
use Doctrine\ORM\EntityManagerInterface;

class CreateProjectService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public function create(CreateProjectDTO $dto): Project
    {
        $project = new Project();
        $project->setTitle($dto->name);
        $project->setSummary($dto->summary);
        $project->setDescription($dto->description);
        $project->setCapacity($dto->capacity);
        $project->setVisibility($dto->visibility);

        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $dto->name)));
        $project->setSlug($slug);
        $project->setStartDate(new \DateTimeImmutable());
        $project->setEndDate($dto->endDate);
        if ($dto->remotePossible !== null) {
        $project->enableRemoteWork();
}
        if ($dto->publishedAt !== null) {
        $project->publish();
}
        if ($dto->deletedAt !== null) {
        $project->softDelete();
}
        $project->setOwnerAccount($dto->ownerAccount);
        $project->setOwnerOrganisation($dto->ownerOrganisation);
        $project->setStatus($dto->status);

        $this->entityManager->persist($project);
        $this->entityManager->flush();

        return $project;
    }
}