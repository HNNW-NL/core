<?php


namespace App\Twig\Components\Org;

use App\Repository\Project\ProjectParticipantRepository;
use App\Repository\Project\ProjectRoleRepository;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class ModifyParticipantsTable
{
    use DefaultActionTrait;

    public string $projectId = '';
    public function __construct(readonly private ProjectParticipantRepository $projectParticipantRepository,
                                readonly private ProjectRoleRepository $projectRoleRepository)
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

    }
}
