<?php

namespace App\Module\Main\Handler;

use App\Entity\Account\Profile;
use App\Entity\Project\Project;
use App\Module\Main\DTO\WorkPackageTaskEnrollmentResult;
use App\Module\Main\Service\WorkPackageTaskEnrollmentService;
use Symfony\Component\Uid\Uuid;

final readonly class WorkPackageTaskEnrollmentHandler
{
    public function __construct(
        private WorkPackageTaskEnrollmentService $workPackageTaskEnrollmentService,
    ) {
    }

    public function handle(Project $project, Uuid|string $taskId, Profile $profile): WorkPackageTaskEnrollmentResult
    {
        return $this->workPackageTaskEnrollmentService->enroll($project, $taskId, $profile);
    }
}