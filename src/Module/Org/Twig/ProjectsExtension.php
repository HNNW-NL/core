<?php
namespace App\Module\Org\Twig;

use App\Module\Org\Service\ProjectsViewService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ProjectsExtension extends AbstractExtension
{
    private ProjectsViewService $service;

    public function __construct(ProjectsViewService $service)
    {
        $this->service = $service;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('org_projects', [$this, 'getProjects']),
        ];
    }

    public function getProjects(string $q = ''): array
    {
        return $this->service->getProjects($q);
    }
}
