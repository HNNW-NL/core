<?php
namespace App\Module\Org\Twig;

use App\Module\Org\Service\ProjectsViewService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * ProjectsExtension
 * 
 * Twig extension that exposes the org_projects() function for templates.
 * Allows templates to fetch and display projects with optional search filtering.
 */
final class ProjectsExtension extends AbstractExtension
{
    private ProjectsViewService $service;

    public function __construct(ProjectsViewService $service)
    {
        $this->service = $service;
    }

    /**
     * Register Twig functions provided by this extension
     * 
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('org_projects', [$this, 'getProjects']),
        ];
    }

    /**
     * Twig function: org_projects(q)
     * 
     * @param string $q Optional search query
     * @return array Project data array from service
     */
    public function getProjects(string $q = ''): array
    {
        return $this->service->getProjects($q);
    }
}
