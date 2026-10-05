<?php

namespace App\Module\Org\Service;

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
        // de controller vult deze drie uit de ingelogde gebruiker, zonder kan het project niet opgeslagen worden
        if ($dto->ownerAccount === null || $dto->ownerOrganisation === null || $dto->status === null) {
            throw new \RuntimeException('Account, organisatie en status zijn nodig om een project aan te maken.');
        }

        $project = new Project();

        $project->setTitle($dto->name);
        $project->setSummary($dto->summary);        
        $project->setDescription($dto->description);
        // de kolom capacity mag niet leeg zijn, dus zonder invulling slaan we 0 op
        $project->setCapacity($dto->capacity ?? 0);
        $project->setVisibility($dto->visibility);

        $project->setSlug($this->makeSlug((string) $dto->name));

        $project->setStartDate(new \DateTimeImmutable());

        if ($dto->endDate !== null) {
            $project->setEndDate($dto->endDate);
        }

        $project->setOwnerAccount($dto->ownerAccount);
        $project->setOwnerOrganisation($dto->ownerOrganisation);
        $project->setStatus($dto->status);

        $this->entityManager->persist($project);
        $this->entityManager->flush();

        return $project;
    }

    // maakt van de naam een slug voor in de url, alles behalve letters en cijfers wordt een streepje
    public function makeSlug(string $name): string
    {
        return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
    }
}
