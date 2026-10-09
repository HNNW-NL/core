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

    #[LiveProp(writable: true)]
    public array $updateMap = [];

    public string $errorMessage = '';





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
    public function updateProjectUpdate(#[LiveArg] string $id): void
    {
        if (!$this->authorizationChecker->isGranted('PROJECT_EDIT', $this->getProject())) {
            $this->errorMessage = 'You are not allowed to edit updates for this project';
            return;
        }
        if (!isset($this->updateMap[$id])) {
            return;
        }

        $updateData = $this->updateMap[$id];
        $updateData['id'] = $id;

        $this->modifyProjectUpdatesHandler->handleModifyUpdate($updateData, $this->getProject());
    }

    #[LiveAction]
    public function deleteProjectUpdate(#[LiveArg] string $id): void
    {
        if (!$this->authorizationChecker->isGranted('PROJECT_EDIT', $this->getProject())) {
            $this->errorMessage = 'You are not allowed to edit updates for this project';
            return;
        }
        $this->modifyProjectUpdatesHandler->deleteProjectUpdateById($id);
    }

    #[LiveAction]
    public function restoreProjectUpdate( #[LiveArg] string $id): void
    {
        if (!$this->authorizationChecker->isGranted('PROJECT_EDIT', $this->getProject())) {
            $this->errorMessage = 'You are not allowed to edit updates for this project';
            return;
        }
        $this->modifyProjectUpdatesHandler->restoreProjectUpdateById($id);
    }

}
