<?php


namespace App\Twig\Components\Org;

use App\Entity\Project\Project;
use App\Repository\Project\ProjectRepository;
use App\Repository\Project\ProjectParticipantRepository;
use App\Repository\Project\ProjectRoleRepository;
use App\Module\Org\Handler\ProjectParticipantUpdateRoleHandler;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

#[AsLiveComponent]
class ModifyParticipantsTable
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public array $rolesByParticipant = [];

    #[LiveProp]
    public string $projectId = '';

    #[LiveProp]
    public string $errorMessage = '';

    public bool $submitted = false;

    public function __construct(readonly private ProjectParticipantRepository $projectParticipantRepository,
                                readonly private ProjectRoleRepository $projectRoleRepository,
                                readonly private ProjectParticipantUpdateRoleHandler $projectParticipantUpdateRoleHandler,
                                readonly private ProjectRepository $projectRepository,
                                readonly private AuthorizationCheckerInterface $authorizationChecker,)
    {
    }

    public function getParticipants(): array
    {
        return $this->projectParticipantRepository->findBy(['project' => $this->projectId]);
    }

    public function getRoles(): array
    {
        return $this->projectRoleRepository->findBy(['project' => $this->projectId]);
    }

    public function getProject(): ?Project
    {
        $project = $this->projectRepository->find($this->projectId);
        if (!$project) {
            $this->errorMessage = 'No project found';
            return null;
        }
        return $project;
    }

    #[LiveAction]
    public function updateParticipants(): void
    {
        $project = $this->getProject();
        if ($project === null){
            return;
        }
        if (!$this->authorizationChecker->isGranted('PROJECT_EDIT', $project)) {
            $this->errorMessage = 'You are not allowed to modify participants for this project';
            return;
        }
        $this->projectParticipantUpdateRoleHandler->handle($this->rolesByParticipant, $project);
        $this->submitted = true;
    }
}
