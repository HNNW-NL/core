<?php

namespace App\Module\Main\Handler;

use App\Entity\Account\Profile;
use App\Entity\Project\Project;
use App\Module\Main\DTO\ProjectApplyDto;
use App\Module\Main\DTO\ProjectApplyResult;
use App\Module\Main\Service\ProjectApplyService;

final readonly class ProjectApplyHandler
{
    public function __construct(
        private ProjectApplyService $projectApplyService,
    ) {
    }

    public function check(Project $project, Profile $profile): ?ProjectApplyResult
    {
        return $this->projectApplyService->check($project, $profile);
    }

    public function handle(Project $project, Profile $profile, ProjectApplyDto $dto): ProjectApplyResult
    {
        return $this->projectApplyService->apply($project, $profile, $dto);
    }
}
