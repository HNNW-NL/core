<?php

namespace App\Twig\Components\Org;

use App\Repository\Project\ProjectRepository;
use App\Entity\Project\Project;
use App\Module\Org\Service\ProjectLifecycle;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use App\Security\CurrentAccountProvider;
use Doctrine\ORM\QueryBuilder;
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

    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly ProjectManagementAccess $projectAccess,
        private readonly CurrentAccountProvider $accountProvider,
    ) {}

    #[LiveAction]
    public function reset(): void
    {
        $this->query = '';
        $this->status = '';
        $this->cardSize = 'medium';
        $this->maxResultsPerPage = 16;
        $this->resetPage();
    }

    public function resetPage(): void
    {
        $this->projectAmount = null;
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

    private ?int $projectAmount = null;

    private function overviewQuery(bool $filterStatus): QueryBuilder
    {
        $query = $this->projectRepository->organisationOverviewQuery(
            $this->query,
            $this->projectAccess->manageableOrganisations(),
            $this->accountProvider->getAccount(),
        );
        if ($filterStatus && $this->status !== '') {
            $query->andWhere('status.name = :statusName')->setParameter('statusName', $this->status);
        }

        return $query;
    }

    public function canCreate(): bool
    {
        return $this->projectAccess->creationOrganisations() !== [];
    }

    public function hasPublicPage(Project $project): bool
    {
        return ProjectLifecycle::isPubliclyVisible($project);
    }

    public function getStatuses(): array
    {
        $statuses = $this->overviewQuery(false)
            ->select('DISTINCT status.name')
            ->andWhere("status.name <> ''")
            ->getQuery()
            ->getSingleColumnResult();
        sort($statuses, SORT_NATURAL | SORT_FLAG_CASE);

        return $statuses;
    }

    public function getProjectAmount(): int
    {
        return $this->projectAmount ??= (int) $this->overviewQuery(true)
            ->select('COUNT(project.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getPageCount(): int
    {
        return (int) ceil($this->getProjectAmount() / $this->getSafeMaxResultsPerPage());
    }

    public function getProjects(): array
    {
        $limit = $this->getSafeMaxResultsPerPage();

        return $this->projectRepository->findOrganisationOverviewPage(
            $this->overviewQuery(true),
            ($this->getSafePage() - 1) * $limit,
            $limit,
        );
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
