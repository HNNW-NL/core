<?php

namespace App\Module\Main\Handler;

use App\Entity\Common\Status;
use App\Entity\Project\WorkPackage;
use App\Module\Main\DTO\ChangeWorkPackageStatusDTO;
use App\Module\Main\Service\WorkPackageWorkflowService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class ChangeWorkPackageStatusHandler
{
    public function __construct(
        private readonly WorkPackageWorkflowService $workflowService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function handle(ChangeWorkPackageStatusDTO $dto): void
    {
        if (!Uuid::isValid($dto->workPackageId)) {
            throw new \InvalidArgumentException('Invalid work package id.');
        }

        $workPackage = $this->entityManager->find(
            WorkPackage::class,
            Uuid::fromString($dto->workPackageId),
        );

        if (!$workPackage instanceof WorkPackage) {
            throw new \InvalidArgumentException('Work package not found.');
        }

        $currentStatus = $workPackage->getStatus()?->getName();

        if ($currentStatus === null) {
            throw new \InvalidArgumentException('Work package has no status.');
        }

        $newStatus = $this->entityManager
            ->getRepository(Status::class)
            ->findOneBy([
                'name' => $dto->statusName,
                'scope' => 'work_package',
            ]);

        if (!$newStatus instanceof Status) {
            throw new \InvalidArgumentException('Work package status not found.');
        }

        if (!$this->workflowService->canTransition($currentStatus, $newStatus->getName())) {
            throw new \InvalidArgumentException(sprintf(
                'Cannot change work package status from "%s" to "%s".',
                $currentStatus,
                $newStatus->getName(),
            ));
        }

        $workPackage->setStatus($newStatus);
        $this->entityManager->flush();
    }
}