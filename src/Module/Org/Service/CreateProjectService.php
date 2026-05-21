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

        $this->entityManager->persist($project);
        $this->entityManager->flush();

        return $project;
    }
}