<?php

namespace App\Module\Main\Service;

use App\Entity\Account\Profile;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectApplication;
use App\Entity\Project\ProjectParticipant;
use App\Module\Main\DTO\ProjectApplyDto;
use App\Module\Main\DTO\ProjectApplyResult;
use App\Module\Main\DTO\ProjectApplyStatus;
use App\Repository\Common\StatusRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ProjectApplyService
{
    // zo heet de status in StatusFixtures, als het goed is is dit de status van een nieuwe aanmelding
    private const SEND_STATUS_SCOPE = 'ProjectApplication';
    private const SEND_STATUS_NAME = 'send';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private StatusRepository $statusRepository,
    ) {
    }

    // geeft een fout terug als dit profiel zich niet mag aanmelden, en null als het wel mag
    public function check(Project $project, Profile $profile): ?ProjectApplyResult
    {
        if ($project->getVisibility() === 'private') {
            return new ProjectApplyResult(
                ProjectApplyStatus::NotOpen,
                'Dit project neemt geen aanmeldingen aan.',
            );
        }

        if ($this->isParticipant($project, $profile)) {
            return new ProjectApplyResult(
                ProjectApplyStatus::AlreadyParticipant,
                'Je doet al mee aan dit project.',
            );
        }

        $application = $this->findApplication($project, $profile);

        if ($application !== null && $application->getDeletedAt() === null) {
            return new ProjectApplyResult(
                ProjectApplyStatus::AlreadyApplied,
                'Je hebt al een aanmelding voor dit project.',
                $application,
            );
        }

        return null;
    }

    public function apply(Project $project, Profile $profile, ProjectApplyDto $dto): ProjectApplyResult
    {
        $error = $this->check($project, $profile);

        if ($error !== null) {
            return $error;
        }

        $status = $this->statusRepository->findOneByScopeAndName(self::SEND_STATUS_SCOPE, self::SEND_STATUS_NAME);

        if ($status === null) {
            return new ProjectApplyResult(
                ProjectApplyStatus::StatusNotFound,
                'Aanmelden lukt nu niet. Probeer het later opnieuw.',
            );
        }

        // de database staat maar 1 aanmelding per project en profiel toe (ook geannuleerd), dus een oude zet ik terug
        $application = $this->findApplication($project, $profile);

        if ($application === null) {
            $application = new ProjectApplication();
            $application->setProject($project);
            $application->setProfile($profile);
            $this->entityManager->persist($application);
        } else {
            $application->restore();
            $application->clearReviewDate();
            $application->setReviewerProfile(null);
            $application->setReview(null);
        }

        $application->setStatus($status);
        $application->setMotivation(trim((string) $dto->motivation));

        // bij een dubbele klik kan de tweede tegen de unique regel botsen, dat is dan gewoon al aangemeld
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return new ProjectApplyResult(
                ProjectApplyStatus::AlreadyApplied,
                'Je hebt al een aanmelding voor dit project.',
            );
        }

        return new ProjectApplyResult(
            ProjectApplyStatus::Success,
            'Je aanmelding is verstuurd.',
            $application,
        );
    }

    // findActiveParticipant in ProjectParticipantRepository is nog leeg (team 4), dus zelf gezocht: leftAt leeg = doet nog mee
    private function isParticipant(Project $project, Profile $profile): bool
    {
        $participant = $this->entityManager->getRepository(ProjectParticipant::class)->findOneBy([
            'project' => $project,
            'profile' => $profile,
            'leftAt' => null,
        ]);

        return $participant !== null;
    }

    // zoekt ook geannuleerde aanmeldingen, daarom geen check op deletedAt
    private function findApplication(Project $project, Profile $profile): ?ProjectApplication
    {
        return $this->entityManager->getRepository(ProjectApplication::class)->findOneBy([
            'project' => $project,
            'profile' => $profile,
        ]);
    }
}
