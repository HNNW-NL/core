<?php


namespace App\Module\Org\Handler;

use App\Repository\Project\ProjectParticipantRepository;
use App\Repository\Project\ProjectRoleRepository;
use Doctrine\ORM\EntityManagerInterface;

class ProjectParticipantUpdateRoleHandler
{
    public function __construct(
        readonly private ProjectParticipantRepository $participantRepo,
        readonly private ProjectRoleRepository $roleRepo,
        readonly private EntityManagerInterface $em
    ) {}

    /**
     * @param array<int, int> $roleMap [participantId => roleId]
     */
    public function handle(array $roleMap, $project): void
    {
        foreach ($roleMap as $participantId => $roleId) {

            $participant = $this->participantRepo->find($participantId);
            $role = $this->roleRepo->find($roleId);

            if (!$participant) {
                continue;
            }
            if ($participant->getProject() !== $project) {
                continue;
            }
            if ($role->getProject() !== $project) {
                continue;
            }

            $participant->setRole($role);
        }

        $this->em->flush();
    }
}
