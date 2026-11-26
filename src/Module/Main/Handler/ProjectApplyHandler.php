<?php

namespace App\Module\Main\Handler;

use App\Entity\Account\Profile;
use App\Entity\Project\Project;
use App\Module\Main\DTO\ProjectApplyDto;
use App\Module\Main\DTO\ProjectApplyResult;
use App\Module\Main\Service\ProjectApplyService;
use App\Module\Main\Service\ProjectApplicationService;
use App\Module\Main\DTO\ProjectApplyStatus;

final readonly class ProjectApplyHandler
{
    public function __construct(
        private ProjectApplyService $projectApplyService,
        private ProjectApplicationService $applications,
    ) {
    }

    public function check(Project $project, Profile $profile): ?ProjectApplyResult
    {
        $existing = $this->applications->findForProfile($project, $profile);
        if ($existing !== null) {
            return new ProjectApplyResult(ProjectApplyStatus::AlreadyApplied,
                'Je aanvraag heeft de status '.$this->applications->label($existing).'.', $existing);
        }
        return $this->projectApplyService->check($project, $profile);
    }

    public function handle(Project $project, Profile $profile, ProjectApplyDto $dto): ProjectApplyResult
    {
        try {
            $submission = $this->applications->submitWithResult($project, $profile->getAccount(), $profile, (string) $dto->motivation);
            return new ProjectApplyResult($submission->created ? ProjectApplyStatus::Success : ProjectApplyStatus::AlreadyApplied,
                $submission->created ? 'Je aanvraag is ingediend en staat in Mijn aanmeldingen.'
                    : 'Je had je al aangemeld voor dit project. Je bestaande aanvraag staat in Mijn aanmeldingen.',
                $submission->application);
        } catch (\DomainException $exception) {
            return new ProjectApplyResult(ProjectApplyStatus::NotOpen, $exception->getMessage());
        }
    }
}
