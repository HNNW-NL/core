<?php

namespace App\Module\Org\Security;

use App\Entity\Project\Project;
use App\Entity\Account\Profile;
use SebastianBergmann\CodeCoverage\StaticAnalysis\Visibility;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use App\Module\Main\Service\CurrentProfileProvider;


abstract class ProjectVoter extends Voter
{
    public const EDIT = 'PROJECT_EDIT';

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, [self::EDIT])) {
            return false;
        }

        return true;
    }


    private function canView(Profile $profile, Project $project,): bool
    {
        // if they can edit, they can view
        if ($this->canEdit($profile,$project)) {
            return true;
        }

        $visibility = $project->getVisibility();
        return $visibility;
    }

    private function canEdit(Profile $profile, Project $project): bool
    {
        if ($profile === $project->getParticipants()) {
            return true;
        }

        return false;
    }

}
