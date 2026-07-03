<?php

namespace App\Module\Main\Twig;

use App\Entity\Account\Profile;
use App\Module\Main\DTO\WorkPackageOverview;
use App\Module\Main\Service\CurrentProfileProvider;
use App\Module\Main\Service\WorkPackageOverviewService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class WorkPackageExtension extends AbstractExtension
{
    public function __construct(
        private readonly WorkPackageOverviewService $workPackageOverviewService,
        private readonly CurrentProfileProvider $currentProfileProvider,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('hnnw_work_package_overviews', [$this, 'getWorkPackageOverviews']),
            new TwigFunction('hnnw_current_profile', [$this, 'getCurrentProfile']),
        ];
    }

    /**
     * @return list<WorkPackageOverview>
     */
    public function getWorkPackageOverviews(string $projectSlug): array
    {
        return $this->workPackageOverviewService->getForProjectSlug($projectSlug);
    }

    public function getCurrentProfile(): ?Profile
    {
        return $this->currentProfileProvider->getProfile();
    }
}