<?php

namespace App\Module\Org\Handler;

use App\Entity\Project\Project;
use App\Entity\Project\WorkPackage;
use App\Module\Main\DTO\CreatePackageTaskDTO;
use App\Module\Main\DTO\CreateWorkPackageDTO;
use App\Module\Main\DTO\UpdateWorkPackageDTO;
use App\Module\Main\Service\PackageTaskCrudService;
use App\Module\Main\Service\PackageTaskInputValidator;
use App\Module\Main\Service\WorkPackageCrudService;
use App\Repository\Project\WorkPackageRepository;
use App\Security\Voter\WorkPackageVoter;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Ulid;

final readonly class ManageProjectWorkPackagesHandler
{
    public function __construct(
        private WorkPackageRepository $repository,
        private WorkPackageCrudService $packages,
        private PackageTaskCrudService $tasks,
        private AuthorizationCheckerInterface $authorization,
        private PackageTaskInputValidator $taskValidator,
    ) {
    }

    public function handle(Project $project, InputBag $input): void
    {
        if ($input->has('responsibleProfileId') && trim($input->getString('responsibleProfileId')) !== '') {
            throw new \InvalidArgumentException('Een werkpakketverantwoordelijke kan pas worden opgeslagen na de databasewijziging door de Product Owner.');
        }
        $action = $input->getString('_action');
        if (!in_array($action, ['create_work_package', 'update_work_package', 'create_package_task', 'delete_work_package'], true)) {
            throw new \InvalidArgumentException('Onbekende bewerking.');
        }
        $permission = $action === 'delete_work_package' ? WorkPackageVoter::DELETE : WorkPackageVoter::EDIT;
        $package = $action === 'create_work_package' ? null : $this->findPackage($project, $input->getString('workPackageId'), $permission);
        if ($action === 'delete_work_package') {
            $this->packages->delete((string) $package->getId(), $project);
            return;
        }
        if ($action === 'create_package_task') {
            $this->tasks->create(new CreatePackageTaskDTO(
                (string) $package->getId(),
                $input->getString('taskTitle'),
                $input->getString('taskSlug'),
                $input->getString('taskDescription'),
                $this->taskValidator->parseDueDate($input->getString('taskDueDate')),
                $input->getString('taskPriority', PackageTaskInputValidator::DEFAULT_PRIORITY),
            ), $project);
            return;
        }

        $title = trim($input->getString('title'));
        $slug = trim($input->getString('slug'));
        $description = trim($input->getString('description'));
        $dueDate = $this->parseDate($input->getString('dueDate'));

        if ($package === null) {
            $this->packages->create(new CreateWorkPackageDTO((string) $project->getId(), $title, $slug, $description, $dueDate), $project);
        } else {
            $this->packages->update(new UpdateWorkPackageDTO((string) $package->getId(), $title, $slug, $description, $dueDate), $project);
        }
    }

    private function findPackage(Project $project, string $id, string $permission): WorkPackage
    {
        if (!Ulid::isValid($id)) {
            throw new \InvalidArgumentException('Ongeldig werkpakket.');
        }
        $package = $this->repository->findOneBy(['id' => Ulid::fromString($id), 'project' => $project, 'deletedAt' => null]);
        if ($package === null) {
            throw new \InvalidArgumentException('Werkpakket niet gevonden in dit project.');
        }
        if (!$this->authorization->isGranted($permission, $package)) {
            throw new AccessDeniedException('Je hebt onvoldoende rechten voor deze bewerking.');
        }
        return $package;
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }
        try {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        } catch (\ValueError) {
            $date = false;
        }
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new \InvalidArgumentException('Vul een geldige deadline in.');
        }
        return $date;
    }
}
