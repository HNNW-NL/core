
<?php

namespace App\Module\Admin\Service;

use App\Entity\Common\Status;
use App\Entity\Project\Project;
use App\Entity\Project\WorkPackage;
use App\Module\Admin\DTO\CreateWorkPackageDTO;
use App\Module\Admin\DTO\UpdateWorkPackageDTO;
use App\Module\Main\Service\WorkPackageWorkflowService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class WorkPackageCrudService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly WorkPackageWorkflowService $workflowService,
    ) {
    }

    /**
     * Create a new work package.
     * New work packages always start with Draft status.
     */
    public function create(CreateWorkPackageDTO $dto): WorkPackage
    {
        if (!Uuid::isValid($dto->projectId)) {
            throw new \InvalidArgumentException(
                'Invalid project id.'
            );
        }

        $project = $this->entityManager->find(
            Project::class,
            Uuid::fromString($dto->projectId)
        );

        if (!$project) {
            throw new \InvalidArgumentException(
                'Project not found.'
            );
        }

        $status = $this->entityManager
            ->getRepository(Status::class)
            ->findOneByScopeAndName('work_package', 'Draft');

        if (!$status) {
            throw new \InvalidArgumentException(
                'Default work package status not found.'
            );
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

    /**
     * Update the general information of a work package.
     * Status changes are handled separately by changeStatus().
     */
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

    /**
     * Change the status of a work package.
     * Only transitions defined in WorkPackageWorkflowService are allowed.
     */
    public function changeStatus(
        string $workPackageId,
        string $newStatusName
    ): WorkPackage {
        $workPackage = $this->findWorkPackage($workPackageId);

        $currentStatus = $workPackage->getStatus()?->getName();

        if (!$currentStatus) {
            throw new \InvalidArgumentException(
                'Current work package status not found.'
            );
        }

        if (!$this->workflowService->canTransition(
            $currentStatus,
            $newStatusName
        )) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Transition from "%s" to "%s" is not allowed.',
                    $currentStatus,
                    $newStatusName
                )
            );
        }

        $newStatus = $this->entityManager
            ->getRepository(Status::class)
            ->findOneByScopeAndName(
                'work_package',
                $newStatusName
            );

        if (!$newStatus) {
            throw new \InvalidArgumentException(
                'Requested work package status not found.'
            );
        }

        $workPackage->setStatus($newStatus);

        $this->entityManager->flush();

        return $workPackage;
    }

    /**
     * Return the allowed next statuses for a work package.
     */
    public function getAllowedStatusTransitions(
        string $workPackageId
    ): array {
        $workPackage = $this->findWorkPackage($workPackageId);

        $currentStatus = $workPackage->getStatus()?->getName();

        if (!$currentStatus) {
            throw new \InvalidArgumentException(
                'Current work package status not found.'
            );
        }

        return $this->workflowService->getAllowedTransitions(
            $currentStatus
        );
    }

    /**
     * Soft-delete a work package.
     */
    public function delete(string $workPackageId): void
    {
        $workPackage = $this->findWorkPackage($workPackageId);

        $workPackage->softDelete();

        $this->entityManager->flush();
    }

    /**
     * Find a work package by UUID.
     */
    private function findWorkPackage(string $workPackageId): WorkPackage
    {
        if (!Uuid::isValid($workPackageId)) {
            throw new \InvalidArgumentException(
                'Invalid work package id.'
            );
        }

        $workPackage = $this->entityManager->find(
            WorkPackage::class,
            Uuid::fromString($workPackageId)
        );

        if (!$workPackage) {
            throw new \InvalidArgumentException(
                'Work package not found.'
            );
        }

        return $workPackage;
    }
}
