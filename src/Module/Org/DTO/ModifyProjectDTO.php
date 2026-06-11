<?php

namespace App\Module\Org\DTO;

use App\Entity\Account\Account;
use App\Entity\Common\Status;

class ModifyProjectDTO
{
    public function __construct(
        // Identity
        public int $projectId,
        public int $organisationId,

        // Core modification
        public Status $status,

        // Optional metadata being changed
        public ?string $description = null,
        public ?string $summary = null,

        // Notification
        public bool $notifyTeam = false,
        public ?string $modifyMessage = null,
        public ?string $notificationEmail = null,

        // Scheduling
        public ?\DateTimeImmutable $scheduledFor = null,
        public ?Account $modifiedBy = null,

        // Timestamps of the project itself (read-only context, not changed here)
        public ?\DateTimeImmutable $timeCreated = null,
        public ?\DateTimeImmutable $timeModified = null,

        // When this modification was submitted
        public ?\DateTimeImmutable $modifiedAt = null,
    ) {
    }

    public function shouldModifyNow(): bool
    {
        if ($this->status !== Status::PUBLISHED) {
            return false;
        }

        if ($this->scheduledFor === null) {
            return true;
        }

        return $this->scheduledFor <= new \DateTimeImmutable();
    }

    public function shouldSchedule(): bool
    {
        if ($this->status !== Status::SCHEDULED) {
            return false;
        }

        if ($this->scheduledFor === null) {
            return false;
        }

        return $this->scheduledFor > new \DateTimeImmutable();
    }

    public function hasDescriptionChanged(): bool
    {
        return $this->description !== null;
    }

    public function hasSummaryChanged(): bool
    {
        return $this->summary !== null;
    }

    public function hasModifyMessage(): bool
    {
        return $this->modifyMessage !== null && $this->modifyMessage !== '';
    }
}
