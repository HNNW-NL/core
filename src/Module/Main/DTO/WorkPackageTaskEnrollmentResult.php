<?php

namespace App\Module\Main\DTO;

use App\Entity\Project\PackageTask;

final readonly class WorkPackageTaskEnrollmentResult
{
    public function __construct(
        public WorkPackageTaskEnrollmentStatus $status,
        public string $message,
        public ?PackageTask $packageTask = null,
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->status === WorkPackageTaskEnrollmentStatus::Success
            || $this->status === WorkPackageTaskEnrollmentStatus::AlreadyAssignedToCurrentProfile;
    }
}