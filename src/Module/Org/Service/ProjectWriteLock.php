<?php

namespace App\Module\Org\Service;

use App\Entity\Project\Project;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ProjectWriteLock
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    public function run(Project $project, callable $operation): mixed
    {
        return $this->entityManager->getConnection()->transactional(function () use ($project, $operation): mixed {
            $this->entityManager->refresh($project, LockMode::PESSIMISTIC_WRITE);

            return $operation();
        });
    }
}
