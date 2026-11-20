<?php

namespace App\Twig\Components\Org;

use App\Repository\Project\ProjectRepository;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use App\Entity\Project\Project;
use App\Module\Org\Service\ProjectManagementAccess;

#[AsLiveComponent]
class ProjectsView
{
    use DefaultActionTrait;

    #[LiveProp(writable: true, onUpdated: 'resetPage')]
    public string $query = '';

    #[LiveProp(writable: true, onUpdated: 'resetPage')]
    public string $status = '';

    #[LiveProp(writable: true)]
    public string $cardSize = 'medium';

    #[LiveProp(writable: true, onUpdated: 'resetPage')]
    public int $maxResultsPerPage = 16;

    #[LiveProp(writable: true)]
    public int $page = 1;

    public function __construct(readonly private ProjectRepository $projectRepository,
                                private readonly ProjectManagementAccess $projectAccess,)
    {
    }

    #[LiveAction]
    public function reset(): void
    {
        $this->query = '';
        $this->status = '';
        $this->cardSize = 'medium';
        $this->maxResultsPerPage = 16;
        $this->page = 1;
    }

    public function resetPage(): void
    {
        $this->page = 1;
    }

    public function getSafeMaxResultsPerPage(): int
    {
        return max(1, min($this->maxResultsPerPage, 100));
    }

    public function getSafePage(): int
    {
        return max(1, min($this->page, $this->getPageCount()));
    }

    private ?array $manageableProjects = null;

    private function manageableProjects(): array
    {
        return $this->manageableProjects ??= array_values(array_filter(
            $this->projectRepository->findForOrganisationOverview($this->query, $this->projectAccess->organisations()),
            fn (Project $project): bool => $this->projectAccess->canEdit($project),
        ));
    }

    public function canCreate(): bool
    {
        return $this->projectAccess->creationOrganisations() !== [];
    }

    public function getStatuses(): array
    {
        $statuses = array_unique(array_filter(array_map(
            static fn (Project $project): ?string => $project->getStatus()?->getName(),
            $this->manageableProjects(),
        )));
        sort($statuses, SORT_NATURAL | SORT_FLAG_CASE);

        return $statuses;
    }

    private function filteredProjects(): array
    {
        return array_filter($this->manageableProjects(), fn (Project $project): bool =>
            $this->status === '' || $project->getStatus()?->getName() === $this->status);
    }

    public function getProjectAmount(): int
    {
        return count($this->filteredProjects());
    }

    public function getPageCount(): int
    {
        return (int) ceil($this->getProjectAmount() / $this->getSafeMaxResultsPerPage());
    }

    public function getProjects(): array
    {
        return array_slice($this->filteredProjects(), ($this->getSafePage() - 1) * $this->getSafeMaxResultsPerPage(), $this->getSafeMaxResultsPerPage());
    }

    #[LiveAction]
    public function nextPage(): void
    {
        $this->page = min($this->getSafePage() + 1, max(1, $this->getPageCount()));
    }

    #[LiveAction]
    public function previousPage(): void
    {
        $this->page = max(1, $this->getSafePage() - 1);
    }
}
