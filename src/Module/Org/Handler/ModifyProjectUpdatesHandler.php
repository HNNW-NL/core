<?php


namespace App\Module\Org\Handler;

use App\Entity\Project\Project;
use App\Repository\Project\ProjectUpdateRepository;
use Doctrine\ORM\EntityManagerInterface;

class ModifyProjectUpdatesHandler
{
    public function __construct(
        private ProjectUpdateRepository $projectUpdateRepository,
        private EntityManagerInterface $em
    ) {}


    public function handleModifyUpdate(array $updateMap, Project $project): void
    {
        $update = $this->projectUpdateRepository->find($updateMap['id']);

        if (!$update || $update->getProject() !== $project) {
            throw new \InvalidArgumentException('Invalid project update id');
        }
        if (!isset($updateMap['title'], $updateMap['content'])
            || trim($updateMap['title']) === ''
            || trim($updateMap['content']) === ''
        ) {
            throw new \InvalidArgumentException('Title and Content cant be empty');
        }

        $update->setTitle($updateMap['title']);
        $update->setContent($updateMap['content']);

        $isPublic = (bool) ($updateMap['public'] ?? false);

        if ($isPublic && !$update->isPublic()){
            $update->makePublic();
        } elseif (!$isPublic && $update->isPublic()){
            $update->makePrivate();
        }


        $this->em->flush();
    }
    public function deleteProjectUpdateById(string $projectUpdateId, Project $project): void
    {
        $update = $this->projectUpdateRepository->find($projectUpdateId);

        if (!$update || $update->getProject() !== $project) {
            throw new \InvalidArgumentException('Invalid project update id');
        }

        $update->softDelete();
        $this->em->flush();
    }

    public function restoreProjectUpdateById(string $projectUpdateId, Project $project): void
    {
        $update = $this->projectUpdateRepository->find($projectUpdateId);

        if (!$update || $update->getProject() !== $project) {
            throw new \InvalidArgumentException('Invalid project update id');
        }

        $update->restore();
        $this->em->flush();
    }
}
