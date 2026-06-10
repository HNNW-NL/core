<?php

namespace App\Module\Main\Mapper;

use App\Entity\Project\WorkPackage;

class WorkPackageMapper
{
    public function toArray(WorkPackage $workPackage): array
    {
        return [
            'id' => $workPackage->getId()->toRfc4122(),
            'title' => $workPackage->getTitle(),
            'slug' => $workPackage->getSlug(),
            'description' => $workPackage->getDescription(),
            'dueDate' => $workPackage->getDueDate()?->format('Y-m-d'),
            'status' => $workPackage->getStatus()?->getName(),
        ];
    }

    /**
     * @param WorkPackage[] $workPackages
     */
    public function manyToArray(array $workPackages): array
    {
        return array_map(
            fn (WorkPackage $workPackage) => $this->toArray($workPackage),
            $workPackages
        );
    }
}


