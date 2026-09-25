<?php

namespace App\Security\Voter;

use App\Entity\Account\Account;
use App\Entity\Project\PackageTask;
use App\Repository\Project\ProjectParticipantRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class PackageTaskVoter extends Voter
{
    public const VIEW = 'PACKAGE_TASK_VIEW';
    public const EDIT = 'PACKAGE_TASK_EDIT';
    public const DELETE = 'PACKAGE_TASK_DELETE';

    public function __construct(
        private readonly ProjectParticipantRepository $participantRepository
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof PackageTask
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

        /** @var PackageTask $task */
        $task = $subject;

        /*
         * OBJECT-LEVEL CHECK
         *
         * Een PackageTask hoort bij een WorkPackage
         * en een WorkPackage hoort bij een Project.
         */
        $workPackage = $task->getWorkPackage();

        if ($workPackage === null) {
            return false;
        }

        $project = $workPackage->getProject();

        if ($project === null) {
            return false;
        }

        /*
         * De eigenaar van het project heeft toegang
         * tot alle taken binnen zijn eigen project.
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
         * Controleer of de gebruiker participant is
         * van het project waar deze specifieke taak bij hoort.
         */
        $participant = $this->participantRepository
            ->findActiveParticipant($project, $profile);

        if ($participant === null) {
            return false;
        }

        /*
         * Zonder projectrol zijn er geen rechten.
         */
        $role = $participant->getRole();

        if ($role === null) {
            return false;
        }

        $permissionsMask = $role->getPermissionsMask() ?? 0;

        return match ($attribute) {
            self::VIEW => ($permissionsMask & 1) === 1,
            self::EDIT => ($permissionsMask & 2) === 2,
            self::DELETE => ($permissionsMask & 4) === 4,
            default => false,
        };
    }
}