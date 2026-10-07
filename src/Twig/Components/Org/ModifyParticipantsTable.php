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
use Symfony\UX\LiveComponent\Attribute\LiveArg;
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

    #[LiveProp(writable: true)]
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

    public function getProject(): project
    {
        return $this->projectRepository->find($this->projectId);
    }

    #[LiveAction]
    public function updateParticipants(): void
    {
        if (!$this->authorizationChecker->isGranted('PROJECT_EDIT', $this->getProject())) {
            $this->errorMessage = 'You are not allowed to invite profile for this project';
            return;
        }
        $this->projectParticipantUpdateRoleHandler->handle($this->rolesByParticipant, $this->getProject());
        $this->submitted = true;
    }
}
