<?php


namespace App\Twig\Components\Org;

use App\Repository\Project\ProjectParticipantRepository;
use App\Repository\Project\ProjectRoleRepository;
use App\Module\Org\Handler\ProjectParticipantUpdateRoleHandler;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class ModifyParticipantsTable
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public array $rolesByParticipant = [];

    #[LiveProp]
    public string $projectId = '';

    public bool $submitted = false;

    public function __construct(readonly private ProjectParticipantRepository $projectParticipantRepository,
                                readonly private ProjectRoleRepository $projectRoleRepository,
                                readonly private ProjectParticipantUpdateRoleHandler $projectParticipantUpdateRoleHandler)
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

    #[LiveAction]
    public function updateParticipants(): void
    {
        $this->projectParticipantUpdateRoleHandler->handle($this->rolesByParticipant);
        $this->submitted = true;
    }
}
