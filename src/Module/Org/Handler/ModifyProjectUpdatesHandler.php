<?php


namespace App\Module\Org\Handler;

use App\Entity\Project\Project;
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
    public function handleModifyUpdate(array $updateMap, Project $project): void
    {
        $update = $this->projectUpdateRepository->find($updateMap['id']);

        if (!$update || $update->getProject() !== $project) {
            return; // throw an exception
        }
        if (!isset($updateMap['title'], $updateMap['content'])) {
            return; // throw en exception
        }

        $update->setTitle($updateMap['title']);
        $update->setContent($updateMap['content']);

        $isPublic = (bool) ($updateMap['public'] ?? false);

        if ($isPublic && !$update->isPublic()){
            $update->makePublic();
        } elseif ($isPublic && $update->isPublic()){
            $update->makePrivate();
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
