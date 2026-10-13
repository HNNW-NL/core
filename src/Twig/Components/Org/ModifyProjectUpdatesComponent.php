<?php

namespace App\Twig\Components\Org;

use App\Entity\Project\Project;
use App\Repository\Project\ProjectRepository;
use App\Repository\Project\ProjectUpdateRepository;
use App\Module\Org\Handler\ModifyProjectUpdatesHandler;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[AsLiveComponent]
class ModifyProjectUpdatesComponent
{
    use DefaultActionTrait;

    #[LiveProp]
    public string $projectId = '';

    #[LiveProp(writable: true)]
    public array $updateMap = [];

    #[LiveProp]
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

    public function getProject(): Project
    {
        return $this->projectRepository->find($this->projectId) ?? throw new NotFoundHttpException();
    }

    public function mount(): void
    {
        foreach ($this->getProjectUpdates() as $update) {
            $this->updateMap[(string) $update->getId()] = [
                'title' => $update->getTitle(),
                'content' => $update->getContent(),
                'public' => $update->isPublic(),
            ];
        }
    }

    #[LiveAction]
    public function updateProjectUpdate(#[LiveArg] string $id): void
    {
        if (!$this->authorizationChecker->isGranted('PROJECT_EDIT', $this->getProject())) {
            $this->errorMessage = 'You are not allowed to edit updates for this project';
            return;
        }
        if (!isset($this->updateMap[$id])) {
            $this->errorMessage = 'Update not found';
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
        if (!isset($this->updateMap[$id])) {
            $this->errorMessage = 'Update not found';
            return;
        }

        $this->modifyProjectUpdatesHandler->deleteProjectUpdateById($id, $this->getProject());
    }

    #[LiveAction]
    public function restoreProjectUpdate( #[LiveArg] string $id): void
    {
        if (!$this->authorizationChecker->isGranted('PROJECT_EDIT', $this->getProject())) {
            $this->errorMessage = 'You are not allowed to edit updates for this project';
            return;
        }
        if (!isset($this->updateMap[$id])) {
            $this->errorMessage = 'Update not found';
            return;
        }

        $this->modifyProjectUpdatesHandler->restoreProjectUpdateById($id, $this->getProject());
    }

}
