<?php

namespace App\Twig\Components\Org;

use App\Repository\Project\ProjectRepository;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use App\Module\Main\Service\CurrentProfileProvider;

#[AsLiveComponent]
class ProjectsView
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $query = '';

    #[LiveProp(writable: true)]
    public string $cardSize = 'medium';

    #[LiveProp(writable: true)]
    public int $maxResults = 30;

    public function __construct(readonly private ProjectRepository $projectRepository,
                                private readonly CurrentProfileProvider $currentProfileProvider,)
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
        $profile = $this->currentProfileProvider->getProfile();
        if (!$profile) {
            return [];
        }

        return $this->projectRepository->findByProfileOnQuery($this->query,$profile);
    }
}
