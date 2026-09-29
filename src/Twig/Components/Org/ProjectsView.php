<?php

namespace App\Twig\Components\Org;

use App\Repository\Project\ProjectRepository;
use App\Repository\Project\ProjectParticipantRepository;
use App\Repository\Account\ProfileRepository;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\Bundle\SecurityBundle\Security;

#[AsLiveComponent]
class ProjectsView
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $query = '';

    #[LiveProp(writable: true)]
    public string $cardSize = 'medium';

    public function __construct(readonly private ProjectRepository $projectRepository,
                                readonly private ProfileRepository $profileRepository,
                                private readonly ProjectParticipantRepository $projectParticipantRepository,)
    {
    }

    #[LiveAction]
    public function reset(): void
    {
        $this->query = '';
        $this->cardSize = 'medium';
    }

    public function getProjects(): array
    {
        $profile = $this->profileRepository->findOneBy([]); // use symfony security to find logged-in user $this->security->getUser()

        return $this->projectRepository->findByProfileOnQuery($this->query,$profile);
    }
}
