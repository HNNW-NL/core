<?php

namespace App\Security\Voter;

use App\Entity\Account\Account;
use App\Entity\Project\WorkPackage;
use App\Repository\Project\ProjectParticipantRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class WorkPackageVoter extends Voter
{
    public const VIEW = 'WORK_PACKAGE_VIEW';
    public const EDIT = 'WORK_PACKAGE_EDIT';
    public const DELETE = 'WORK_PACKAGE_DELETE';

    public function __construct(
        private readonly ProjectParticipantRepository $participantRepository
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof WorkPackage
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

        /** @var WorkPackage $workPackage */
        $workPackage = $subject;

        /*
         * OBJECT-LEVEL CHECK
         *
         * Een WorkPackage hoort altijd bij een specifiek Project.
         * Daarom controleren we de rechten via dat project.
         */
        $project = $workPackage->getProject();

        if ($project === null) {
            return false;
        }

        /*
         * De eigenaar van het project heeft toegang tot
         * de WorkPackages van zijn eigen project.
         */
        if ($project->getOwnerAccount() === $user) {
            return true;
        }

        /*
         * Andere gebruikers moeten een profiel hebben.
         */
        $profile = $user->getProfile();

        if ($profile === null) {
            return false;
        }

        /*
         * Controleer of deze gebruiker daadwerkelijk participant
         * is van het project waar DIT WorkPackage bij hoort.
         */
        $participant = $this->participantRepository
            ->findActiveParticipant($project, $profile);

        if ($participant === null) {
            return false;
        }

        /*
         * Zonder projectrol krijgt de participant geen rechten.
         */
        $role = $participant->getRole();

        if ($role === null) {
            return false;
        }

        /*
         * Gebruik dezelfde permissionsMask als de projectrol.
         */
        $permissionsMask = $role->getPermissionsMask() ?? 0;

        return match ($attribute) {
            self::VIEW => ($permissionsMask & 1) === 1,
            self::EDIT => ($permissionsMask & 2) === 2,
            self::DELETE => ($permissionsMask & 4) === 4,
            default => false,
        };
    }
}