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
    public int $maxResultsPerPage = 16;

    #[LiveProp(writable: true)]
    public int $page = 1;

    public function __construct(readonly private ProjectRepository $projectRepository,
                                private readonly CurrentProfileProvider $currentProfileProvider,)
    {
    }

    #[LiveAction]
    public function reset(): void
    {
        $this->query = '';
        $this->cardSize = 'medium';
        $this->maxResultsPerPage = 16;
        $this->page = 1;
    }

    public function getProjectAmount(): ?int
    {
        $profile = $this->currentProfileProvider->getProfile();
        if (!$profile) {
            return 0;
        }

        return count($this->projectRepository->findByProfileOnQuery($this->query, $profile));
    }

    public function getPageCount(): int
    {
        return $this->getProjectAmount() / $this->maxResultsPerPage;
    }

    public function getProjects(): array
    {
        $profile = $this->currentProfileProvider->getProfile();
        if (!$profile) {
            return [];
        }

        return $this->projectRepository->findPaginatedByProfileOnQuery($this->query, $profile, $this->maxResultsPerPage, $this->page);
    }

    #[LiveAction]
    public function nextPage(): void
    {
        if ($this->page + 1 > $this->getPageCount()){
            return;
        }
        $this->page += 1;
    }

    #[LiveAction]
    public function lastPage(): void
    {
        if ($this->page - 1 <= 0){
            return;
        }
        $this->page -= 1;
    }
}
