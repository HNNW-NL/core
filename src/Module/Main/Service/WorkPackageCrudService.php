<?php

namespace App\Module\Main\Service;

use App\Entity\Project\Project;
use App\Entity\Project\PackageTask;
use App\Entity\Project\WorkPackage;
use App\Module\Main\DTO\CreateWorkPackageDTO;
use App\Repository\Common\StatusRepository;
use App\Security\Permission;
use App\Security\Voter\ProjectVoter;
use App\Security\Voter\WorkPackageVoter;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use App\Module\Main\DTO\UpdateWorkPackageDTO;

class WorkPackageCrudService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StatusRepository $statusRepository,
        private readonly AuthorizationCheckerInterface $authorization,
    ) {
    }

    public function create(CreateWorkPackageDTO $dto, ?Project $project = null): WorkPackage
    {
        if (!Ulid::isValid($dto->projectId)) {
            throw new \InvalidArgumentException('Invalid project id.');
        }
        if ($project !== null && !Ulid::fromString($dto->projectId)->equals($project->getId())) {
            throw new \InvalidArgumentException('Werkpakket hoort niet bij dit project.');
        }

        return $this->entityManager->getConnection()->transactional(function () use ($dto): WorkPackage {
            $project = $this->entityManager->find(Project::class, Ulid::fromString($dto->projectId), LockMode::PESSIMISTIC_READ);
            if (!$project) {
                throw new \InvalidArgumentException('Project not found.');
            }
            $this->assertProjectAccess($project);

            $status = $this->statusRepository->findFirstByScopeAndNames(WorkPackageWorkflowService::STATUS_SCOPE, ['Open', 'active'])
                ?? $this->statusRepository->findFirstByScopeAndNames('WorkPackage', ['Open', 'active']);
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
        });
    }

    public function update(UpdateWorkPackageDTO $dto, ?Project $project = null): WorkPackage
    {
        return $this->entityManager->getConnection()->transactional(function () use ($dto, $project): WorkPackage {
            $workPackage = $this->findWorkPackage($dto->workPackageId, $project, WorkPackageVoter::EDIT);
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
            $workPackage = $this->findWorkPackage($workPackageId, $project, WorkPackageVoter::DELETE);
            // Count archived tasks as well to preserve their history and results.
            if ($this->entityManager->getRepository(PackageTask::class)->count(['workPackage' => $workPackage]) > 0) {
                throw new \InvalidArgumentException('Een werkpakket met taken kan niet worden verwijderd.');
            }
            $workPackage->softDelete();
            $this->entityManager->flush();
        });
    }

    private function findWorkPackage(string $workPackageId, ?Project $project, string $permission): WorkPackage
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
        $actualProject = $workPackage->getProject();
        if ($actualProject === null || ($project !== null && !$actualProject->getId()->equals($project->getId()))) {
            throw new \InvalidArgumentException('Werkpakket hoort niet bij dit project.');
        }
        $this->assertProjectAccess($actualProject);
        if (!$this->authorization->isGranted($permission, $workPackage)) {
            throw new AccessDeniedException('Je hebt onvoldoende rechten voor deze bewerking.');
        }
        return $workPackage;
    }

    private function assertProjectAccess(Project $project): void
    {
        if ($project->getDeletedAt() !== null) {
            throw new \InvalidArgumentException('Project is verwijderd.');
        }
        if (!$this->authorization->isGranted(ProjectVoter::EDIT, $project)
            || !$this->authorization->isGranted(Permission::ORG_MANAGE_PROJECTS->name, $project->getOwnerOrganisation())
        ) {
            throw new AccessDeniedException('Je mag de werkpakketten van dit project niet beheren.');
        }
    }

}
