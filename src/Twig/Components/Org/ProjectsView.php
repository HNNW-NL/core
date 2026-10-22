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

    #[LiveProp(writable: true, onUpdated: 1)]
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

    private function getSafeMaxResultsPerPage(): int
    {
        return max(1, min($this->maxResultsPerPage, 100));
    }

    private function getSafePage(): int
    {
        return max(1, $this->page);
    }


    public function getProjectAmount(): int
    {
        $profile = $this->currentProfileProvider->getProfile();
        if (!$profile) {
            return 0;
        }

        return $this->projectRepository->countByProfileOnQuery($this->query, $profile);
    }

    public function getPageCount(): int
    {
        return (int) ceil($this->getProjectAmount() / $this->getSafeMaxResultsPerPage());
    }

    public function getProjects(): array
    {
        $profile = $this->currentProfileProvider->getProfile();
        if (!$profile) {
            return [];
        }

        return $this->projectRepository->findPaginatedByProfileOnQuery(
            $this->query,
            $profile,
            $this->getSafeMaxResultsPerPage(),
            $this->getSafePage());
    }

    #[LiveAction]
    public function nextPage(): void
    {
        if ($this->page + 1 > $this->getPageCount()) {
            return;
        }
        $this->page += 1;
    }

    #[LiveAction]
    public function previousPage(): void
    {
        if ($this->page - 1 <= 0) {
            return;
        }
        $this->page -= 1;
    }
}
