<?php

namespace App\Module\Admin\Service;

use App\Entity\Common\Status;
use App\Entity\Project\PackageTask;
use App\Entity\Project\WorkPackage;
use App\Module\Admin\DTO\CreatePackageTaskDTO;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class PackageTaskCrudService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function create(CreatePackageTaskDTO $dto): PackageTask
    {
        if (!Uuid::isValid($dto->workPackageId)) {
            throw new \InvalidArgumentException('Invalid work package id.');
        }

        $workPackage = $this->entityManager->find(WorkPackage::class, Uuid::fromString($dto->workPackageId));

        if (!$workPackage) {
            throw new \InvalidArgumentException('Work package not found.');
        }

        $task = new PackageTask();
        $task->setWorkPackage($workPackage);
        $task->setStatus($this->findDefaultStatus($workPackage));
        $task->setTitle($dto->title);
        $task->setSlug($dto->slug);
        $task->setDescription($dto->description);
        $task->setDueDate($dto->dueDate);
        $task->setPriority($dto->priority);

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        return $task;
    }

    private function findDefaultStatus(WorkPackage $workPackage): Status
    {
        $statusRepository = $this->entityManager->getRepository(Status::class);

        $status = $statusRepository->findOneBy([
            'name' => 'open',
            'scope' => 'package_task',
        ]) ?? $statusRepository->findOneBy([
            'name' => 'Open',
            'scope' => 'package_task',
        ]) ?? $statusRepository->findOneBy([
            'name' => 'open',
        ]) ?? $statusRepository->findOneBy([
            'name' => 'Open',
        ]) ?? $workPackage->getStatus();

        if (!$status) {
            throw new \InvalidArgumentException('Default task status not found.');
        }

        return $status;
    }
}
