<?php

namespace App\Module\Main\Service;

use App\Entity\Project\WorkPackage;
use App\Module\Main\DTO\CreatePackageTaskDTO;
use App\Repository\Project\PackageTaskRepository;

final readonly class PackageTaskInputValidator
{
    public const DEFAULT_PRIORITY = 'medium';
    public const PRIORITIES = ['low', 'medium', 'high'];

    public function __construct(private PackageTaskRepository $tasks)
    {
    }

    public function validateCreation(CreatePackageTaskDTO $input, WorkPackage $workPackage): CreatePackageTaskDTO
    {
        $title = trim($input->title);
        if (preg_match('/[^\s\p{Z}]/u', $title) !== 1 || mb_strlen($title) > 255) {
            throw new \InvalidArgumentException('Vul een taaktitel van maximaal 255 tekens in.');
        }

        $slug = trim($input->slug);
        if (strlen($slug) > 255 || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug)) {
            throw new \InvalidArgumentException('Gebruik een taakslug met kleine letters, cijfers en enkele koppeltekens (maximaal 255 tekens).');
        }

        // The concrete task DoD is stored in the existing description field.
        $definitionOfDone = trim($input->description ?? '');
        if (preg_match('/[^\s\p{Z}]/u', $definitionOfDone) !== 1) {
            throw new \InvalidArgumentException('Vul een concrete taak-DoD met acceptatiecriteria in.');
        }
        if (!in_array($input->priority, self::PRIORITIES, true)) {
            throw new \InvalidArgumentException('Kies een geldige taakprioriteit: low, medium of high.');
        }

        // Include archived tasks: their slugs and history must remain reserved.
        if ($this->tasks->findOneBy(['workPackage' => $workPackage, 'slug' => $slug]) !== null) {
            throw new \InvalidArgumentException('Deze slug wordt al gebruikt binnen dit werkpakket.');
        }

        return new CreatePackageTaskDTO(
            $input->workPackageId, $title, $slug, $definitionOfDone, $input->dueDate, $input->priority,
        );
    }

    public function parseDueDate(string $value): ?\DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }
        try {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        } catch (\ValueError) {
            $date = false;
        }
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new \InvalidArgumentException('Vul een geldige deadline in.');
        }

        return $date;
    }
}
