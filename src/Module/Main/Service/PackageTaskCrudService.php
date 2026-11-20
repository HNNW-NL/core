<?php

namespace App\Module\Main\Service;

use App\Entity\Common\Status;
use App\Entity\Project\PackageTask;
use App\Entity\Project\WorkPackage;
use App\Module\Main\DTO\CreatePackageTaskDTO;
use App\Repository\Common\StatusRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

class PackageTaskCrudService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StatusRepository $statusRepository,
    ) {
    }

    public function create(CreatePackageTaskDTO $dto): PackageTask
    {
        if (!Ulid::isValid($dto->workPackageId)) {
            throw new \InvalidArgumentException('Invalid work package id.');
        }

        return $this->entityManager->getConnection()->transactional(function () use ($dto): PackageTask {
            $workPackage = $this->entityManager->find(
                WorkPackage::class,
                Ulid::fromString($dto->workPackageId),
                LockMode::PESSIMISTIC_WRITE,
            );

            if (!$workPackage || $workPackage->getDeletedAt() !== null || $workPackage->getProject()?->getDeletedAt() !== null) {
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
        });
    }

    private function findDefaultStatus(): Status
    {
        $status = $this->statusRepository->findFirstByScopeAndNames('package_task', ['Open', 'active'])
            ?? $this->statusRepository->findFirstByScopeAndNames('PackageTask', ['Open', 'active']);

        if (!$status) {
            throw new \InvalidArgumentException('Default task status not found.');
        }

        return $status;
    }
}
