<?php

namespace App\Module\Admin\Service;

use App\Entity\Common\Status;
use App\Entity\Project\PackageTask;
use App\Entity\Project\WorkPackage;
use App\Module\Admin\DTO\CreatePackageTaskDTO;
use App\Enum\PackageTaskWorkflowStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

class PackageTaskCrudService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function create(CreatePackageTaskDTO $dto): PackageTask
    {
        if (!Ulid::isValid($dto->workPackageId)) {
            throw new \InvalidArgumentException('Invalid work package id.');
        }

        $workPackage = $this->entityManager->find(WorkPackage::class, Ulid::fromString($dto->workPackageId));

        if (!$workPackage) {
            throw new \InvalidArgumentException('Work package not found.');
        }

        $task = new PackageTask();
        $task->setWorkPackage($workPackage);
        $task->setStatus($this->findDefaultStatus());
        $task->setTitle($dto->title);
        $task->setSlug($dto->slug);
        $task->setDescription($dto->description);
        $task->setDueDate($dto->dueDate);
        $task->setPriority($dto->priority);

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        return $task;
    }

    private function findDefaultStatus(): Status
    {
        $statusRepository = $this->entityManager->getRepository(Status::class);

        $status = $statusRepository->findOneBy([
            'name' => PackageTaskWorkflowStatus::OPEN->value,
            'scope' => PackageTaskWorkflowStatus::STATUS_SCOPE,
        ]) ?? $statusRepository->findOneBy([
            'name' => strtolower(PackageTaskWorkflowStatus::OPEN->value),
            'scope' => PackageTaskWorkflowStatus::STATUS_SCOPE,
        ]);

        if (!$status) {
            $status = (new Status())
                ->setName(PackageTaskWorkflowStatus::OPEN->value)
                ->setScope(PackageTaskWorkflowStatus::STATUS_SCOPE)
                ->setColourHex('#10B981');
            $this->entityManager->persist($status);
        }

        return $status;
    }
}
