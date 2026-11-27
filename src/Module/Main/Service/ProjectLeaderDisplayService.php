<?php

namespace App\Module\Main\Service;

use App\Entity\Project\Project;
use App\Enum\VisibilityLevel;

final class ProjectLeaderDisplayService
{
    public function getDisplayName(Project $project): ?string
    {
        $account = $project->getOwnerAccount();

        if ($account === null || $account->getDeletedAt() !== null) {
            return null;
        }

        $profile = $account->getProfile();
        $setting = $account->getSetting();

        if (
            $profile === null
            || $profile->getDeletedAt() !== null
            || $setting === null
            || $setting->getProfileVisibility() !== VisibilityLevel::PUBLIC->value
        ) {
            return null;
        }

        $displayName = trim((string) $profile->getDisplayName());

        return $displayName !== '' ? $displayName : null;
    }
}
