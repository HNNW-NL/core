<?php

namespace App\Module\Org\DTO;

use App\Entity\Account\Account;
use App\Entity\Common\Status;

class ModifyProjectDTO
{
    public function __construct(
        public int $projectId,
        public int $organisationId,
        public Status $status,
        public bool $notifyTeam = false,
        public ?string $publishMessage = null,
        public ?string $notificationEmail = null,
        public ?\DateTimeImmutable $scheduledFor = null,
        public ?Account $publishedBy = null,
    ) {
    }

    public function shouldPublishNow(): bool
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
}
