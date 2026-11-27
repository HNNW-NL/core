<?php

namespace App\Module\Main\Service;

use Doctrine\DBAL\LockMode;
use App\Entity\Account\Profile;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectApplication;
use App\Repository\Project\ProjectParticipantRepository;
use App\Module\Main\DTO\ProjectApplyDto;
use App\Module\Main\DTO\ProjectApplyResult;
use App\Module\Main\DTO\ProjectApplyStatus;
use App\Repository\Common\StatusRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ProjectApplyService
{
    private const string SEND_STATUS_SCOPE = 'ProjectApplication';
    private const string SEND_STATUS_NAME = 'send';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private StatusRepository $statusRepository,
        private ProjectParticipantRepository $projectParticipantRepository,
    ) {
    }

    public function isOpen(Project $project): bool
    {
        if (
            $project->getVisibility() !== 'public'
            || $project->getPublishedAt() === null
            || $project->getDeletedAt() !== null
            || !in_array(
                strtolower((string) $project->getStatus()?->getName()),
                ['active', 'published'],
                true,
            )
            || (
                $project->getEndDate() !== null
                && $project->getEndDate() < new \DateTimeImmutable('today')
            )
            || $project->getOwnerOrganisation() === null
            || $project->getOwnerOrganisation()->getDeletedAt() !== null
        ) {
            return false;
        }

        if ($project->getCapacity() === null) {
            return true;
        }

        $activeParticipants = (int) $this->projectParticipantRepository
            ->createQueryBuilder('participant')
            ->select('COUNT(participant.id)')
            ->andWhere('participant.project = :project')
            ->andWhere('participant.joinedAt IS NOT NULL')
            ->andWhere('participant.leftAt IS NULL')
            ->setParameter('project', $project->getId(), 'ulid')
            ->getQuery()
            ->getSingleScalarResult();

        return $activeParticipants < $project->getCapacity();
    }

    public function check(Project $project, Profile $profile): ?ProjectApplyResult
    {
        if ($this->projectParticipantRepository->isActiveParticipant($project, $profile)) {
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

        if (!$this->isOpen($project)) {
            return new ProjectApplyResult(
                ProjectApplyStatus::NotOpen,
                'Dit project neemt geen aanmeldingen aan.',
            );
        }

        return null;
    }

    public function apply(
        Project $project,
        Profile $profile,
        ProjectApplyDto $dto
    ): ProjectApplyResult {
        return $this->entityManager->wrapInTransaction(
            function () use ($project, $profile, $dto): ProjectApplyResult {
                $this->entityManager->refresh(
                    $project,
                    LockMode::PESSIMISTIC_WRITE
                );

                return $this->doApply($project, $profile, $dto);
            }
        );
    }

    private function doApply(
        Project $project,
        Profile $profile,
        ProjectApplyDto $dto
    ): ProjectApplyResult {
        $error = $this->check($project, $profile);

        if ($error !== null) {
            return $error;
        }

        $status = $this->statusRepository->findOneByScopeAndName(
            self::SEND_STATUS_SCOPE,
            self::SEND_STATUS_NAME
        );

        if ($status === null) {
            return new ProjectApplyResult(
                ProjectApplyStatus::StatusNotFound,
                'Aanmelden lukt nu niet. Probeer het later opnieuw.',
            );
        }

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

        return new ProjectApplyResult(
            ProjectApplyStatus::Success,
            'Je aanmelding is verstuurd.',
            $application,
        );
    }

    // zoekt ook geannuleerde aanmeldingen, daarom geen check op deletedAt
    private function findApplication(
        Project $project,
        Profile $profile
    ): ?ProjectApplication {
        return $this->entityManager->createQueryBuilder()
            ->select('application')
            ->from(ProjectApplication::class, 'application')
            ->where('application.project = :project')
            ->andWhere('application.profile = :profile')
            ->setParameter('project', $project->getId(), 'ulid')
            ->setParameter('profile', $profile->getId(), 'ulid')
            ->getQuery()
            ->setHint(\Doctrine\ORM\Query::HINT_REFRESH, true)
            ->getOneOrNullResult();
    }
}
