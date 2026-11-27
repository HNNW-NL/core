<?php

namespace App\Module\Main\Service;

use App\Repository\Project\ProjectRepository;

final readonly class PublicProjectListingService
{
    private const int PROJECTS_PER_PAGE = 20;

    public function __construct(
        private ProjectRepository $projectRepository,
    ) {
    }

    public function getListing(array $query): array
    {
        $errors = [];

        $rawSearch = $query['q'] ?? '';
        if (!is_string($rawSearch) || preg_match('/\A.{0,100}\z/us', trim($rawSearch)) !== 1) {
            $errors[] = 'Gebruik een zoekterm van maximaal 100 tekens.';
            $search = '';
        } else {
            $search = trim($rawSearch);
        }

        $rawRemoteFilter = $query['remote'] ?? '';
        if (!is_string($rawRemoteFilter) || !in_array($rawRemoteFilter, ['', 'yes', 'no'], true)) {
            $errors[] = 'De gekozen projectfilter is ongeldig.';
            $remoteFilter = '';
        } else {
            $remoteFilter = $rawRemoteFilter;
        }

        $rawOpenFilter = $query['open'] ?? '';
        if (!is_string($rawOpenFilter) || !in_array($rawOpenFilter, ['', '1', '0'], true)) {
            $errors[] = 'De gekozen aanmeldfilter is ongeldig.';
            $rawOpenFilter = '';
        }
        $onlyAcceptingApplications = $rawOpenFilter === '1';

        $rawPage = $query['page'] ?? '1';
        $requestedPage = is_string($rawPage)
            ? filter_var($rawPage, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;
        if ($requestedPage === false) {
            $errors[] = 'Het paginanummer is ongeldig. Je ziet pagina 1.';
            $requestedPage = 1;
        }

        $remotePossible = $remoteFilter === 'yes' ? true : ($remoteFilter === 'no' ? false : null);

        $totalProjects = $this->projectRepository->countPublicProjects(
            $search,
            $onlyAcceptingApplications,
            $remotePossible,
        );

        $totalPages = max(1, (int) ceil($totalProjects / self::PROJECTS_PER_PAGE));
        $page = min(max(1, $requestedPage), $totalPages);

        return [
            'publicProjects' => $this->projectRepository->findPaginatedPublicProjects(
                $search,
                $onlyAcceptingApplications,
                $page,
                self::PROJECTS_PER_PAGE,
                $remotePossible,
            ),
            'search' => $search,
            'onlyAcceptingApplications' => $onlyAcceptingApplications,
            'remoteFilter' => $remoteFilter,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalProjects' => $totalProjects,
            'filterErrors' => $errors,
            'hasFilters' => $search !== '' || $onlyAcceptingApplications || $remoteFilter !== '',
        ];
    }
}
