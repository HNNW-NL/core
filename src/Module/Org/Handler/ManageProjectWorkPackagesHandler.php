<?php

namespace App\Module\Org\Handler;

use App\Entity\Project\Project;
use App\Entity\Project\WorkPackage;
use App\Module\Main\DTO\CreatePackageTaskDTO;
use App\Module\Main\DTO\CreateWorkPackageDTO;
use App\Module\Main\DTO\UpdateWorkPackageDTO;
use App\Module\Main\Service\PackageTaskCrudService;
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
        $isTask = $action === 'create_package_task';
        $title = trim($input->getString($isTask ? 'taskTitle' : 'title'));
        $slug = trim($input->getString($isTask ? 'taskSlug' : 'slug'));
        if ($title === '' || mb_strlen($title) > 255) {
            throw new \InvalidArgumentException('Vul een titel van maximaal 255 tekens in.');
        }
        $unchangedSlug = !$isTask && $package !== null && $slug === $package->getSlug();
        if (!$unchangedSlug && (strlen($slug) > 255 || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug))) {
            throw new \InvalidArgumentException('Gebruik een slug met kleine letters, cijfers en enkele koppeltekens.');
        }
        $description = trim($input->getString($isTask ? 'taskDescription' : 'description'));
        $dueDate = $this->parseDate($input->getString($isTask ? 'taskDueDate' : 'dueDate'));
        if ($isTask) {
            $this->tasks->create(new CreatePackageTaskDTO((string) $package->getId(), $title, $slug, $description, $dueDate, 'normal'), $project);
            return;
        }

        $existing = $this->repository->findOneBy(['project' => $project, 'slug' => $slug]);
        if ($existing !== null && $existing !== $package) {
            throw new \InvalidArgumentException('Deze slug wordt al gebruikt binnen dit project.');
        }
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
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new \InvalidArgumentException('Vul een geldige deadline in.');
        }
        return $date;
    }
}
