<?php

namespace App\Twig\Components\Org;

use App\Repository\Project\ProjectRepository;
use App\Repository\Project\PackageTaskRepository;
use App\Repository\Project\WorkPackageRepository;
use App\Entity\Project\Project;
use App\Entity\Project\PackageTask;
use App\Module\Org\Handler\ModifyTasksHandler;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

#[AsLiveComponent]
class ModifyTaskComponent
{
    use DefaultActionTrait;

    #[LiveProp]
    public string $projectId = '';

    public string $errorMessage = '';

    #[LiveProp(writable: true)]
    public array $updateMap = [];


    public function __construct(readonly private AuthorizationCheckerInterface $authorizationChecker,
                                readonly private ProjectRepository             $projectRepository,
                                readonly private PackageTaskRepository         $packageTaskRepository,
                                readonly private WorkPackageRepository         $workPackageRepository,
                                readonly private ModifyTasksHandler            $modifyTasksHandler,
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

    public function updateTask(#[LiveArg] string $id): void
    {
        if (!$this->authorizationChecker->isGranted('PROJECT_EDIT', $this->getProject())) {
            $this->errorMessage = 'You are not allowed to edit tasks for this project';
            return;
        }
        if (!isset($this->updateMap[$id])) {
            return;
        }

        $updateData = $this->updateMap[$id];
        $updateData['id'] = $id;

        $this->modifyTasksHandler->handleModifyTask($updateData, $this->getProject());
    }

}
