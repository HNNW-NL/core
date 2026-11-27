<?php

namespace App\Security\Voter;

use App\Entity\Account\Account;
use App\Entity\Project\Project;
use App\Security\ProjectPermission;
use App\Repository\Project\ProjectParticipantRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ProjectVoter extends Voter
{
    public const VIEW = 'PROJECT_VIEW';
    public const EDIT = 'PROJECT_EDIT';
    public const DELETE = 'PROJECT_DELETE';

    public function __construct(
        private readonly ProjectParticipantRepository $participantRepository
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Project
            && in_array($attribute, [
                self::VIEW,
                self::EDIT,
                self::DELETE,
            ], true);
    }

    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        TokenInterface $token,
        ?Vote $vote = null
    ): bool {
        $user = $token->getUser();

        if (!$user instanceof Account) {
            return false;
        }

        /** @var Project $project */
        $project = $subject;

        /*
         * De eigenaar van het project heeft volledige toegang
         * tot zijn eigen project.
         */
        if ($project->getOwnerAccount() === $user) {
            return true;
        }

        /*
         * Voor object-level permissions hebben we het profiel
         * van de ingelogde gebruiker nodig.
         */
        $profile = $user->getProfile();

        if ($profile === null) {
            return false;
        }

        /*
         * OBJECT-LEVEL CHECK
         *
         * Controleer of deze gebruiker daadwerkelijk een actieve
         * participant is van DIT specifieke project.
         */
        $permission = match ($attribute) {
            self::VIEW => ProjectPermission::VIEW,
            self::EDIT => ProjectPermission::EDIT,
            self::DELETE => ProjectPermission::DELETE,
            default => null,
        };

        foreach ($this->participantRepository->findActiveParticipantsByProjectAndProfile($project, $profile) as $participant) {
            if ($permission?->isGrantedBy($participant->getRole(), $project)) {
                return true;
            }
        }

        return false;
    }
}
