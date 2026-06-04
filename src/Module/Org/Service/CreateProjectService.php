<?php

namespace App\Module\Org\Service;

use App\Entity\Account\Account;
use App\Entity\Common\Status;
use App\Entity\Org\Organisation;
use App\Entity\Project\Project;
use App\Module\Org\DTO\CreateProjectDTO;
use Doctrine\ORM\EntityManagerInterface;

class CreateProjectService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public function create(CreateProjectDTO $dto): Project
    {
        $project = new Project();

        $project->setTitle($dto->name);
        $project->setSummary($dto->summary);    
        $project->setDescription($dto->description);
        $project->setCapacity($dto->capacity);
        $project->setVisibility($dto->visibility);

        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $dto->name)));
        $project->setSlug($slug);

        $project->setStartDate(new \DateTimeImmutable());

        if ($dto->endDate !== null) {
            $project->setEndDate($dto->endDate);
        }

        $account = $this->entityManager->getRepository(Account::class)->findOneBy([]);
        $organisation = $this->entityManager->getRepository(Organisation::class)->findOneBy([]);
        $status = $this->entityManager->getRepository(Status::class)->findOneBy([]);

        if (!$account || !$organisation || !$status) {
            throw new \RuntimeException('No valid Account/Organisation/Status found in DB');
        }

        $project->setOwnerAccount($account);
        $project->setOwnerOrganisation($organisation);
        $project->setStatus($status);

        $this->entityManager->persist($project);
        $this->entityManager->flush();

        return $project;
    }
}