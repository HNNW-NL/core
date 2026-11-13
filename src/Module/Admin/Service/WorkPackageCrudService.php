<?php

namespace App\Module\Admin\Service;

use App\Entity\Common\Status;
use App\Entity\Project\Project;
use App\Entity\Project\WorkPackage;
use App\Module\Admin\DTO\CreateWorkPackageDTO;
use App\Module\Main\Service\WorkPackageWorkflowService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;
use App\Module\Admin\DTO\UpdateWorkPackageDTO;

class WorkPackageCrudService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function create(CreateWorkPackageDTO $dto): WorkPackage
    {
        if (!Ulid::isValid($dto->projectId)) {
            throw new \InvalidArgumentException('Invalid project id.');
        }

        $project = $this->entityManager->find(Project::class, Ulid::fromString($dto->projectId));

        $status = $this->entityManager->getRepository(Status::class)->findOneBy([
            'name' => 'Open',
            'scope' => WorkPackageWorkflowService::STATUS_SCOPE,
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
    public function update(UpdateWorkPackageDTO $dto): WorkPackage
{
    $workPackage = $this->findWorkPackage($dto->workPackageId);

    $workPackage->setTitle($dto->title);
    $workPackage->setSlug($dto->slug);
    $workPackage->setDescription($dto->description);
    $workPackage->setDueDate($dto->dueDate);

    $this->entityManager->flush();

    return $workPackage;
}

public function delete(string $workPackageId): void
{
    $workPackage = $this->findWorkPackage($workPackageId);
    $workPackage->softDelete();

    $this->entityManager->flush();
}

private function findWorkPackage(string $workPackageId): WorkPackage
{
    if (!Ulid::isValid($workPackageId)) {
        throw new \InvalidArgumentException('Invalid work package id.');
    }

    $workPackage = $this->entityManager->find(WorkPackage::class, Ulid::fromString($workPackageId));

    if (!$workPackage) {
        throw new \InvalidArgumentException('Work package not found.');
    }

    return $workPackage;
}
}
