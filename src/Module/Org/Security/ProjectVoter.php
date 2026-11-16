<?php

namespace App\Module\Org\Security;

use App\Entity\Project\Project;
use App\Entity\Account\Profile;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use App\Module\Main\Service\CurrentProfileProvider;
use App\Repository\Project\ProjectParticipantRepository;
use App\Security\ProjectPermission;

final class ProjectVoter extends Voter
{
    public const string VIEW = 'PROJECT_VIEW';
    public const string EDIT = 'PROJECT_EDIT';
    public const string INVITE = 'PROJECT_INVITE';

    public function __construct(
        private readonly CurrentProfileProvider $currentProfileProvider,
        private readonly ProjectParticipantRepository $participantRepository,
    ) { }


    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Project && in_array($attribute, [ self::VIEW, self::EDIT, self::INVITE, ], true);
    }

    protected function voteOnAttribute( string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $profile = $this->currentProfileProvider->getProfile();
        if (!$profile instanceof Profile)
        {
            return false;
        }

        /** @var Project $project */

        $project = $subject;
        return match ($attribute)
        {
            self::VIEW => $this->hasPermission($profile, $project, ProjectPermission::VIEW),
            self::EDIT => $this->hasPermission($profile, $project, ProjectPermission::EDIT),
            self::INVITE => $this->hasPermission($profile, $project, ProjectPermission::INVITE),
            default => false,
        };
    }

    private function hasPermission(Profile $profile, Project $project, ProjectPermission $permission): bool
    {
        $participant = $this->participantRepository ->findLatestActiveParticipantByProjectAndProfile($project, $profile);
        if ($participant === null)
        {
            return false;
        }

        $role = $participant->getRole();

        if ($role === null)
        {
            return false;
        }

        return $permission->in($role->getPermissionsMask());
    }

}
