<?php

namespace App\Module\Main\Handler;

use App\Entity\Common\Status;
use App\Entity\Project\Project;
use App\Entity\Project\WorkPackage;
use App\Module\Main\DTO\ChangeWorkPackageStatusDTO;
use App\Module\Main\Service\WorkPackageWorkflowService;
use App\Security\Voter\WorkPackageVoter;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Uuid;

final class ChangeWorkPackageStatusHandler
{
    public function __construct(
        private readonly WorkPackageWorkflowService $workflowService,
        private readonly EntityManagerInterface $entityManager,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public function handle(ChangeWorkPackageStatusDTO $dto, Project $project): void
    {
        if (!Uuid::isValid($dto->workPackageId)) {
            throw new \InvalidArgumentException('Invalid work package id.');
        }

        $this->entityManager->getConnection()->transactional(function () use ($dto, $project): void {
            $workPackage = $this->entityManager->find(
                WorkPackage::class,
                Uuid::fromString($dto->workPackageId),
                LockMode::PESSIMISTIC_WRITE,
            );

            if (!$workPackage instanceof WorkPackage || $workPackage->getDeletedAt() !== null
                || $workPackage->getProject() !== $project
            ) {
                throw new \InvalidArgumentException('Work package not found in this project.');
            }

            $this->changeStatus($workPackage, $dto->statusName);
        });
    }

    private function changeStatus(WorkPackage $workPackage, string $statusName): void
    {
        if (!$this->authorizationChecker->isGranted(WorkPackageVoter::EDIT, $workPackage)) {
            throw new AccessDeniedException('You do not have permission to change this work package status.');
        }

        $currentStatus = $workPackage->getStatus();
        $currentName = $currentStatus?->getName();

        if ($currentName === null) {
            throw new \InvalidArgumentException('Work package has no status.');
        }
        if ($currentStatus->getScope() !== WorkPackageWorkflowService::STATUS_SCOPE) {
            throw new \InvalidArgumentException('Current status is outside the work package scope.');
        }

        $newStatus = $this->entityManager
            ->getRepository(Status::class)
            ->findOneBy([
                'name' => $statusName,
                'scope' => WorkPackageWorkflowService::STATUS_SCOPE,
            ]);

        if (!$newStatus instanceof Status) {
            throw new \InvalidArgumentException('Work package status not found.');
        }

        if (!$this->workflowService->canTransition($currentName, $statusName)) {
            throw new \InvalidArgumentException(sprintf(
                'Cannot change work package status from "%s" to "%s".',
                $currentName,
                $statusName,
            ));
        }

        $workPackage->setStatus($newStatus);
        $this->entityManager->flush();
    }
}
