<?php


namespace App\Module\Org\Handler;

use App\Entity\Project\ProjectUpdate;
use App\Repository\Project\ProjectUpdateRepository;
use Doctrine\ORM\EntityManagerInterface;

class ModifyProjectUpdatesHandler
{
    public function __construct(
        private ProjectUpdateRepository $projectUpdateRepository,
        private EntityManagerInterface $em
    ) {}

    /**
     * @param array<int, int> $roleMap [participantId => roleId]
     */
    public function handle(array $updateMap): void
    {
        foreach ($updateMap as $projectUpdate) {

            $update = $this->projectUpdateRepository->find($projectUpdate['id']);
            $update->setTitle($projectUpdate['title']);
            $update->setContent($projectUpdate['content']);

            if ($projectUpdate['public'] and !$update->isPublic()){
                $update->makePublic();
            } elseif (!$projectUpdate['public'] and $update->isPublic()){
                $update->makePrivate();
            }

        }

        $this->em->flush();
    }
    public function deleteProjectUpdateById($projectUpdateId): void
    {
        $update = $this->projectUpdateRepository->find($projectUpdateId);
        $update->softDelete();
    }

    public function restoreProjectUpdateById($projectUpdateId): void
    {
        $update = $this->projectUpdateRepository->find($projectUpdateId);
        $update->restore();
    }
}
