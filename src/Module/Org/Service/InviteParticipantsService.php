<?php

namespace App\Module\Org\Service;

use App\Entity\Project\ProjectParticipant;
use App\Repository\Project\ProjectRepository;
use App\Entity\Common\Status;
use Doctrine\ORM\EntityManagerInterface;

class InviteParticipantsService
{
    public function __construct(
        readonly private EntityManagerInterface $entityManager,
        readonly private ProjectRepository $projectRepository
    ) {}

    public function addToProject(string $projectId, array $profiles): void
    {
        $project = $this->projectRepository->find($projectId);
        if (!$project) {return;}
        foreach ($profiles as $profile)
        {
            $projectParticipant = new ProjectParticipant();
            $projectParticipant->setProfile($profile);
            $projectParticipant->setProject($project);
            $projectParticipant->setStatus(
                $this->entityManager
                    ->getRepository(Status::class)
                    ->findOneBy(['scope' => 'ProjectParticipant']));
            $projectParticipant->setRole(null);
            $projectParticipant->setApplication(null);

            $this->entityManager->persist($projectParticipant);
        }
        $this->entityManager->flush();
    }
}

