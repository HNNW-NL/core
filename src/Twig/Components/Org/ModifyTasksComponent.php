<?php

namespace App\Twig\Components\Org;

use App\Repository\Project\ProjectRepository;
use App\Repository\Project\PackageTaskRepository;
use App\Repository\Project\WorkPackageRepository;
use App\Repository\Common\StatusRepository;
use App\Entity\Project\Project;
use App\Module\Org\Handler\ModifyTasksHandler;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

#[AsLiveComponent]
class ModifyTasksComponent
{
    use DefaultActionTrait;

    #[LiveProp]
    public string $projectId = '';

    public string $errorMessage = '';

    #[LiveProp(writable: true)]
    public array $taskMap = [];


    public function __construct(readonly private AuthorizationCheckerInterface $authorizationChecker,
                                readonly private ProjectRepository             $projectRepository,
                                readonly private PackageTaskRepository         $packageTaskRepository,
                                readonly private WorkPackageRepository         $workPackageRepository,
                                readonly private ModifyTasksHandler            $modifyTasksHandler,
                                readonly private StatusRepository $statusRepository,
    )
    {
    }


    public function getProject(): project
    {
        return $this->projectRepository->find($this->projectId);
    }

    public function getTasks(): iterable
    {
        return $this->packageTaskRepository->findByProject($this->getProject());
    }

    public function getWorkPackages(): iterable
    {
        return $this->workPackageRepository->findActiveByProject($this->getProject());
    }

    public function getStatuses(): iterable
    {
        return $this->statusRepository->findAll();
    }

    public function updateTask(#[LiveArg] string $id): void
    {
        if (!$this->authorizationChecker->isGranted('PROJECT_EDIT', $this->getProject())) {
            $this->errorMessage = 'You are not allowed to edit tasks for this project';
            return;
        }
        if (!isset($this->taskMap[$id])) {
            return;
        }

        $taskData = $this->taskMap[$id];
        $taskData['id'] = $id;

        $this->modifyTasksHandler->handleModifyTask($taskData, $this->getProject());
    }

    #[LiveAction]
    public function deleteProjectTask(#[LiveArg] string $id): void
    {
        if (!$this->authorizationChecker->isGranted('PROJECT_EDIT', $this->getProject())) {
            $this->errorMessage = 'You are not allowed to edit tasks for this project';
            return;
        }
        $this->modifyTasksHandler->deleteTaskById($id, $this->getProject());
    }

    #[LiveAction]
    public function restoreProjectTask( #[LiveArg] string $id): void
    {
        if (!$this->authorizationChecker->isGranted('PROJECT_EDIT', $this->getProject())) {
            $this->errorMessage = 'You are not allowed to edit tasks for this project';
            return;
        }
        $this->modifyTasksHandler->restoreTaskById($id, $this->getProject());
    }

}
