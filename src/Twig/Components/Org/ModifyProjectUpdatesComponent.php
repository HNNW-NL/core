<?php

namespace App\Twig\Components\Org;

use App\Entity\Project\Project;
use App\Entity\Project\ProjectUpdate;
use App\Repository\Project\ProjectRepository;
use App\Repository\Project\ProjectUpdateRepository;
use App\Module\Org\Handler\ModifyProjectUpdatesHandler;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

#[AsLiveComponent]
class ModifyProjectUpdatesComponent
{
    use DefaultActionTrait;

    #[LiveProp]
    public string $projectId = '';

    public string $errorMessage = '';

    public bool $submitted = false;




    public function __construct(readonly private ProjectRepository $projectRepository,
                                readonly private ProjectUpdateRepository $projectUpdateRepository,
                                readonly private AuthorizationCheckerInterface $authorizationChecker,
                                readonly private ModifyProjectUpdatesHandler $modifyProjectUpdatesHandler
    )
    {
    }


    public function getProjectUpdates() : array
    {
        return $this->projectUpdateRepository->findBy(['project' => $this->projectId]);
    }

    public function getProject(): project
    {
        return $this->projectRepository->find($this->projectId);
    }

    #[LiveAction]
    public function updateProjectUpdates($updateMap): void
    {
        if (!$this->authorizationChecker->isGranted('PROJECT_EDIT', $this->getProject())) {
            $this->errorMessage = 'You are not allowed to edit updates for this project';
            return;
        }
        $this->modifyProjectUpdatesHandler->handleModifyUpdate($updateMap, $this->getProject());
        $this->submitted = true;
    }

    #[LiveAction]
    public function deleteProjectUpdate(#[LiveArg] string $projectUpdateId): void
    {
        if (!$this->authorizationChecker->isGranted('PROJECT_EDIT', $this->getProject())) {
            $this->errorMessage = 'You are not allowed to edit updates for this project';
            return;
        }
        $this->modifyProjectUpdatesHandler->deleteProjectUpdateById($projectUpdateId);
    }

    #[LiveAction]
    public function restoreProjectUpdate( #[LiveArg] string$projectUpdateId): void
    {
        if (!$this->authorizationChecker->isGranted('PROJECT_EDIT', $this->getProject())) {
            $this->errorMessage = 'You are not allowed to edit updates for this project';
            return;
        }
        $this->modifyProjectUpdatesHandler->restoreProjectUpdateById($projectUpdateId);
    }

}
