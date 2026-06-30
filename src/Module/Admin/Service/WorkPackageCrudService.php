<?php

namespace App\Module\Admin\Service;

use App\Entity\Common\Status;
use App\Entity\Project\Project;
use App\Entity\Project\WorkPackage;
use App\Module\Admin\DTO\CreateWorkPackageDTO;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class WorkPackageCrudService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function create(CreateWorkPackageDTO $dto): WorkPackage
    {
        if (!Uuid::isValid($dto->projectId)) {
            throw new \InvalidArgumentException('Invalid project id.');
        }

        $project = $this->entityManager->find(Project::class, Uuid::fromString($dto->projectId));

        $status = $this->entityManager->getRepository(Status::class)->findOneBy([
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