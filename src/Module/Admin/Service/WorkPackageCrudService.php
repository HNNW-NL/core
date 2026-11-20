<?php

namespace App\Module\Admin\Service;

use App\Entity\Project\Project;
use App\Entity\Project\PackageTask;
use App\Entity\Project\WorkPackage;
use App\Module\Admin\DTO\CreateWorkPackageDTO;
use App\Module\Main\Service\WorkPackageWorkflowService;
use App\Repository\Common\StatusRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;
use App\Module\Admin\DTO\UpdateWorkPackageDTO;

class WorkPackageCrudService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StatusRepository $statusRepository,
    ) {
    }

    public function create(CreateWorkPackageDTO $dto): WorkPackage
    {
        if (!Ulid::isValid($dto->projectId)) {
            throw new \InvalidArgumentException('Invalid project id.');
        }

        $project = $this->entityManager->find(Project::class, Ulid::fromString($dto->projectId));

        $status = $this->statusRepository->findFirstByScopeAndNames(WorkPackageWorkflowService::STATUS_SCOPE, ['Open', 'active'])
            ?? $this->statusRepository->findFirstByScopeAndNames('WorkPackage', ['Open', 'active']);

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
        return $this->entityManager->getConnection()->transactional(function () use ($dto): WorkPackage {
            $workPackage = $this->findWorkPackage($dto->workPackageId);
            if ($workPackage->getDeletedAt() !== null) {
                throw new \InvalidArgumentException('Werkpakket is verwijderd.');
            }
            $workPackage->setTitle($dto->title);
            $workPackage->setSlug($dto->slug);
            $workPackage->setDescription($dto->description);
            $workPackage->setDueDate($dto->dueDate);
            $this->entityManager->flush();

            return $workPackage;
        });
    }

    public function delete(string $workPackageId, ?Project $project = null): void
    {
        $this->entityManager->getConnection()->transactional(function () use ($workPackageId, $project): void {
            $workPackage = $this->findWorkPackage($workPackageId);
            if ($project !== null && $workPackage->getProject() !== $project) {
                throw new \InvalidArgumentException('Werkpakket hoort niet bij dit project.');
            }
            // Count archived tasks as well to preserve their history and results.
            if ($this->entityManager->getRepository(PackageTask::class)->count(['workPackage' => $workPackage]) > 0) {
                throw new \InvalidArgumentException('Een werkpakket met taken kan niet worden verwijderd.');
            }
            $workPackage->softDelete();
            $this->entityManager->flush();
        });
    }

    private function findWorkPackage(string $workPackageId): WorkPackage
    {
        if (!Ulid::isValid($workPackageId)) {
            throw new \InvalidArgumentException('Invalid work package id.');
        }
        $workPackage = $this->entityManager->find(
            WorkPackage::class,
            Ulid::fromString($workPackageId),
            LockMode::PESSIMISTIC_WRITE,
        );
        if (!$workPackage) {
            throw new \InvalidArgumentException('Work package not found.');
        }
        return $workPackage;
    }
}
