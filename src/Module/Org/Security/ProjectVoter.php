<?php

namespace App\Module\Org\Security;

use App\Entity\Project\Project;
use App\Entity\Account\Profile;
use SebastianBergmann\CodeCoverage\StaticAnalysis\Visibility;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use App\Module\Main\Service\CurrentProfileProvider;
use App\Repository\Project\ProjectParticipantRepository;

abstract class ProjectVoter extends Voter
{
    public const string VIEW = 'PROJECT_VIEW';
    public const string EDIT = 'PROJECT_EDIT';
    public const string INVITE = 'PROJECT_INVITE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (in_array($attribute, [self::VIEW, self::EDIT, self::INVITE]) && $subject instanceof Project) {
            return true;
        }

        return false;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $profile = CurrentProfileProvider::class->getProfile();
        $project = $subject;

        return match ($attribute) {
            self::VIEW => $this->canView($profile, $project),
            self::INVITE => $this->canInvite($profile, $project),
            self::EDIT => $this->canEdit($profile, $project)
        };

    }




    private function canView(Profile $profile, Project $project): bool
    {
        // if they can edit, they can view
        if ($this->canEdit($profile,$project)) {
            return true;
        }
        if (ProjectParticipantRepository::class->isActiveParticipant($project,$profile)) {
            return true;
        }

        return false;
    }

    private function canEdit(Profile $profile, Project $project): bool
    {
        if ($profile === $project->getParticipants()) {
            return true;
        }

        return false;
    }

    private function canInvite(Profile $profile, Project $project): bool
    {
        if ($profile === $project->getParticipants()) {
            return true;
        }
        return false;
    }

}
