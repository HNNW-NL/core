<?php

namespace App\Module\Main\DTO;

use App\Entity\Project\ProjectApplication;

final readonly class ProjectApplyResult
{
    public function __construct(
        public ProjectApplyStatus $status,
        public string $message,
        public ?ProjectApplication $application = null,
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->status === ProjectApplyStatus::Success;
    }
}
