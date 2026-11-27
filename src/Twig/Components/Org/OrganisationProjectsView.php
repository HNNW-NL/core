<?php

namespace App\Twig\Components\Org;

use App\Entity\Org\Organisation;
use App\Repository\Project\ProjectRepository;
use App\Security\Permission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Uid\Ulid;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\PostHydrate;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class OrganisationProjectsView
{
    use DefaultActionTrait;

    #[LiveProp]
    public string $organisationId = '';

    #[LiveProp(writable: true, onUpdated: 'resetPage')]
    public string $query = '';

    #[LiveProp(writable: true)]
    public string $cardSize = 'medium';

    #[LiveProp(writable: true, onUpdated: 'resetPage')]
    public int $maxResultsPerPage = 16;

    #[LiveProp(writable: true)]
    public int $page = 1;

    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    #[PostHydrate]
    public function checkOrganisationAccess(): void
    {
        $this->getAuthorisedOrganisation();
    }

    private function getAuthorisedOrganisation(): Organisation
    {
        if (!$this->authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw new AccessDeniedHttpException('Je moet ingelogd zijn.');
        }

        try {
            $id = Ulid::fromString($this->organisationId);
        } catch (\InvalidArgumentException) {
            throw new AccessDeniedHttpException('Ongeldige organisatie.');
        }

        $organisation = $this->entityManager->find(Organisation::class, $id);

        if (
            !$organisation instanceof Organisation
            || $organisation->getDeletedAt() !== null
            || !$this->authorizationChecker->isGranted(
                Permission::ORG_MANAGE_PROJECTS->name,
                $organisation,
            )
        ) {
            throw new AccessDeniedHttpException('Geen toegang tot deze organisatie.');
        }

        return $organisation;
    }

    #[LiveAction]
    public function reset(): void
    {
        $this->query = '';
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

    public function getProjectAmount(): int
    {
        return $this->projectRepository->countByOrganisationOnQuery(
            $this->query,
            $this->getAuthorisedOrganisation(),
        );
    }

    public function getPageCount(): int
    {
        return (int) ceil(
            $this->getProjectAmount() / $this->getSafeMaxResultsPerPage()
        );
    }

    public function getProjects(): array
    {
        return $this->projectRepository->findPaginatedByOrganisationOnQuery(
            $this->query,
            $this->getAuthorisedOrganisation(),
            $this->getSafeMaxResultsPerPage(),
            $this->getSafePage(),
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
