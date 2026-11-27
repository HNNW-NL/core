<?php

namespace App\Module\Main\Service;

use App\Entity\Common\Status;
use App\Entity\Project\PackageTask;
use App\Entity\Project\Project;
use App\Entity\Project\WorkPackage;
use App\Module\Main\DTO\CreatePackageTaskDTO;
use App\Repository\Common\StatusRepository;
use App\Security\Permission;
use App\Security\Voter\ProjectVoter;
use App\Security\Voter\WorkPackageVoter;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class PackageTaskCrudService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StatusRepository $statusRepository,
        private readonly AuthorizationCheckerInterface $authorization,
        private readonly PackageTaskInputValidator $validator,
    ) {
    }

    public function create(CreatePackageTaskDTO $dto, ?Project $project = null): PackageTask
    {
        if (!Ulid::isValid($dto->workPackageId)) {
            throw new \InvalidArgumentException('Invalid work package id.');
        }

        return $this->entityManager->getConnection()->transactional(function () use ($dto, $project): PackageTask {
            $workPackage = $this->entityManager->find(
                WorkPackage::class,
                Ulid::fromString($dto->workPackageId),
                LockMode::PESSIMISTIC_WRITE,
            );

            if (!$workPackage || $workPackage->getDeletedAt() !== null) {
                throw new \InvalidArgumentException('Work package not found.');
            }
            $actualProject = $workPackage->getProject();
            if ($actualProject === null || $actualProject->getDeletedAt() !== null) {
                throw new \InvalidArgumentException('Project not found.');
            }
            if ($project !== null && !$actualProject->getId()->equals($project->getId())) {
                throw new \InvalidArgumentException('Werkpakket hoort niet bij dit project.');
            }
            if (!$this->authorization->isGranted(ProjectVoter::EDIT, $actualProject)
                || !$this->authorization->isGranted(Permission::ORG_MANAGE_PROJECTS->name, $actualProject->getOwnerOrganisation())
                || !$this->authorization->isGranted(WorkPackageVoter::EDIT, $workPackage)
            ) {
                throw new AccessDeniedException('Je mag geen taken aanmaken binnen dit werkpakket.');
            }

            $validated = $this->validator->validateCreation($dto, $workPackage);

            $task = new PackageTask();
            $task->setWorkPackage($workPackage);
            $task->setStatus($this->findDefaultStatus());
            $task->setTitle($validated->title);
            $task->setSlug($validated->slug);
            $task->setDescription($validated->description);
            $task->setDueDate($validated->dueDate);
            $task->setPriority($validated->priority);

            $this->entityManager->persist($task);
            $this->entityManager->flush();

            return $task;
        });
    }

    private function findDefaultStatus(): Status
    {
        $status = $this->statusRepository->findOneBy(['scope' => 'PackageTask', 'name' => 'active']);

        if (!$status) {
            throw new \InvalidArgumentException('Default task status not found.');
        }

        return $status;
    }
}
