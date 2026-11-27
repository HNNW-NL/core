<?php

namespace App\Module\Main\Service;

use App\Entity\Account\Profile;
use App\Entity\Project\Project;
use App\Module\Main\DTO\ProjectApplyDto;
use App\Module\Main\DTO\ProjectApplyResult;
use App\Module\Main\DTO\ProjectApplyStatus;

final readonly class ProjectApplyService
{
    public function __construct(private ProjectApplicationService $applications)
    {
    }

    public function check(Project $project, Profile $profile): ?ProjectApplyResult
    {
        return $this->applications->check($project, $profile);
    }

    public function apply(Project $project, Profile $profile, ProjectApplyDto $dto): ProjectApplyResult
    {
        $account = $profile->getAccount();
        if ($account === null) {
            return new ProjectApplyResult(ProjectApplyStatus::NoProfile, 'Je hebt een actief profiel nodig om je aan te melden.');
        }
        try {
            $submission = $this->applications->submitWithResult($project, $account, $profile, (string) $dto->motivation);
            return new ProjectApplyResult($submission->created ? ProjectApplyStatus::Success : ProjectApplyStatus::AlreadyApplied,
                $submission->created ? 'Je aanvraag is ingediend en staat in Mijn aanmeldingen.'
                    : 'Je had je al aangemeld voor dit project. Je bestaande aanvraag staat in Mijn aanmeldingen.',
                $submission->application);
        } catch (\DomainException $exception) {
            return new ProjectApplyResult(ProjectApplyStatus::NotOpen, $exception->getMessage());
        }
    }
}
