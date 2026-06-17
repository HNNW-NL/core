<?php

namespace App\Module\Org\DTO;

use App\Entity\Account\Account;

class ModifyProjectDTO
{
    public function __construct(
        public string $projectId,
        public string $organisationId,

        public ?string $title = null,
        public ?string $summary = null,
        public ?string $description = null,
        public ?string $visibility = null,
        public ?string $statusName = null,
        public ?\DateTimeImmutable $startDate = null,
        public ?\DateTimeImmutable $endDate = null,
        public ?int $capacity = null,
        public ?bool $remotePossible = null,
        public ?Account $modifiedBy = null,
        public ?\DateTimeImmutable $timeCreated = null,
        public ?\DateTimeImmutable $timeModified = null,
        public ?\DateTimeImmutable $modifiedAt = null,
    ) {
    }

    public function hasTitleChanged(): bool
    {
        return $this->title !== null && $this->title !== '';
    }

    public function hasSummaryChanged(): bool
    {
        return $this->summary !== null && $this->summary !== '';
    }

    public function hasDescriptionChanged(): bool
    {
        return $this->description !== null && $this->description !== '';
    }

    public function hasVisibilityChanged(): bool
    {
        return $this->visibility !== null && $this->visibility !== '';
    }

    public function hasStatusChanged(): bool
    {
        return $this->statusName !== null && $this->statusName !== '';
    }

    public function hasStartDateChanged(): bool
    {
        return $this->startDate !== null;
    }

    public function hasEndDateChanged(): bool
    {
        return $this->endDate !== null;
    }

    public function hasCapacityChanged(): bool
    {
        return $this->capacity !== null;
    }

    public function hasRemotePossibleChanged(): bool
    {
        return $this->remotePossible !== null;
    }
}
