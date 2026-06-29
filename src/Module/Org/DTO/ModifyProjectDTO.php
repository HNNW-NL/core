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
        public bool $startDateTouched = false,
        public bool $endDateTouched = false,
        public bool $visibilityTouched = false,
        public ?int $capacity = null,
        public ?Account $modifiedBy = null,
        public ?\DateTimeImmutable $modifiedAt = null,
    ) {
        if ($projectId === '' || $organisationId === '') {
            throw new \InvalidArgumentException('Project ID and Organisation ID cannot be empty');
        }
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
        return $this->visibilityTouched;
    }

    public function hasStatusNameChanged(): bool
    {
        return $this->statusName !== null && $this->statusName !== '';
    }

    public function hasStartDateChanged(): bool
    {
        return $this->startDateTouched;
    }

    public function hasEndDateChanged(): bool
    {
        return $this->endDateTouched;
    }

    public function hasCapacityChanged(): bool
    {
        return $this->capacity !== null;
    }
}
