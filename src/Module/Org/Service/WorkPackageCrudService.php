<?php

namespace App\Module\Org\Service;

use App\Entity\Project\WorkPackage;
use App\Module\Org\DTO\CreateWorkPackageDTO;
use App\Repository\Common\StatusRepository;
use App\Repository\Project\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class WorkPackageCrudService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProjectRepository $projectRepository,
        private readonly StatusRepository $statusRepository,
    ) {
    }

    public function create(CreateWorkPackageDTO $dto): WorkPackage
    {
        $project = $this->projectRepository->find(Uuid::fromString($dto->projectId));

        $status = $this->statusRepository->findOneBy([
            'name' => 'open',
            'scope' => 'work_package',
        ]);

        if (!$project) {
            throw new \InvalidArgumentException('Project not found.');
        }

        if (!$status) {
            throw new \InvalidArgumentException('Default work package status not found.');
        }

        $workPackage = new WorkPackage();
        $workPackage->setProject($project);
        $workPackage->setStatus($status);
        $workPackage->setTitle($dto->title);
        $workPackage->setSlug($dto->slug);
        $workPackage->setDescription($dto->description);
        $workPackage->setDueDate($dto->dueDate);

        $this->entityManager->persist($workPackage);
        $this->entityManager->flush();

        return $workPackage;
    }
}