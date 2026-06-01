<?php


namespace App\Module\Org\Handler;

use App\Repository\Project\ProjectParticipantRepository;
use App\Repository\Project\ProjectRoleRepository;
use Doctrine\ORM\EntityManagerInterface;

class ProjectParticipantUpdateRoleHandler
{
    public function __construct(
        private ProjectParticipantRepository $participantRepo,
        private ProjectRoleRepository $roleRepo,
        private EntityManagerInterface $em
    ) {}

    /**
     * @param array<int, int> $roleMap [participantId => roleId]
     */
    public function handle(array $roleMap): void
    {
        foreach ($roleMap as $participantId => $roleId) {

            $participant = $this->participantRepo->find($participantId);
            $role = $this->roleRepo->find($roleId);

            if (!$participant) {
                continue;
            }

            $participant->setRole($role);
        }

        $this->em->flush();
    }
}
