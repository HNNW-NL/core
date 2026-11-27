<?php

namespace App\Module\Main\Service;

use App\Entity\Account\Account;
use App\Entity\Account\Profile;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectApplication;
use App\Repository\Common\StatusRepository;
use App\Repository\Project\ProjectParticipantRepository;
use App\Module\Main\DTO\ProjectApplicationSubmission;
use App\Module\Main\DTO\ProjectApplyResult;
use App\Module\Main\DTO\ProjectApplyStatus;
use App\Workflow\ProjectApplicationWorkflow as ApplicationWorkflow;
use App\Module\Main\Exception\ProjectApplicationBlocked;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class ProjectApplicationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private StatusRepository $statuses,
        private ProjectParticipantRepository $participants,
    ) {
    }

    public function submit(Project $project, Account $account, Profile $profile, string $motivation): ProjectApplication
    {
        return $this->submitWithResult($project, $account, $profile, $motivation)->application;
    }

    public function findForProfile(Project $project, Profile $profile, bool $refresh = false): ?ProjectApplication
    {
        $application = $this->em->getRepository(ProjectApplication::class)->findOneBy(['project' => $project, 'profile' => $profile]);
        if ($refresh && $application !== null) {
            $this->em->refresh($application);
        }
        return $application;
    }

    public function submitWithResult(Project $project, Account $account, Profile $profile, string $motivation): ProjectApplicationSubmission
    {
        $motivation = trim($motivation);
        if (mb_strlen($motivation) < 20 || mb_strlen($motivation) > 2000) {
            throw new \DomainException('De motivatie moet 20 tot en met 2.000 tekens bevatten.');
        }
        $result = $this->em->wrapInTransaction(function () use ($project, $account, $profile, $motivation): ProjectApplicationSubmission|ProjectApplyResult|AccessDeniedHttpException {
            $this->em->lock($project, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($project);
            $this->em->refresh($account);
            $this->em->refresh($profile);
            if ($profile->getAccount()?->getId() != $account->getId()) {
                return new AccessDeniedHttpException('Gebruik je eigen actieve profiel.');
            }
            $blocked = $this->check($project, $profile, true);
            if ($blocked !== null) {
                $existing = $blocked->application;
                if ($blocked->status === ProjectApplyStatus::AlreadyApplied && $existing !== null
                    && $existing->getDeletedAt() === null && $this->pending($existing)) {
                    return new ProjectApplicationSubmission($existing, false);
                }
                return $blocked;
            }
            $status = $this->statuses->findFirstByScopeAndNames(ApplicationWorkflow::APPLICATION_SCOPE, ApplicationWorkflow::PENDING_NAMES);
            if ($status === null) {
                return new ProjectApplyResult(ProjectApplyStatus::StatusNotFound, 'De aanvraagstatus is nog niet geconfigureerd.');
            }
            $application = $this->findForProfile($project, $profile, true);
            if ($application !== null) {
                $application->restore();
                $application->clearReviewDate();
                $application->setReviewerProfile(null)->setReview(null);
            } else {
                $application = (new ProjectApplication())->setProject($project)->setProfile($profile);
                $this->em->persist($application);
            }
            $application->setStatus($status)->setMotivation($motivation);
            return new ProjectApplicationSubmission($application, true);
        });
        if ($result instanceof AccessDeniedHttpException) {
            throw $result;
        }
        if ($result instanceof ProjectApplyResult) {
            throw new ProjectApplicationBlocked($result);
        }
        return $result;
    }

    public function check(Project $project, Profile $profile, bool $refreshApplication = false): ?ProjectApplyResult
    {
        $account = $profile->getAccount();
        if ($account === null || $account->getDeletedAt() !== null || $profile->getDeletedAt() !== null) {
            return new ProjectApplyResult(ProjectApplyStatus::NoProfile, 'Je hebt een actief beschikbaar profiel nodig.');
        }
        if ($account->getEmailVerifiedAt() === null) {
            return new ProjectApplyResult(ProjectApplyStatus::EmailNotVerified, 'Bevestig eerst je e-mailadres.');
        }
        if ($project->getOwnerAccount()?->getId() == $account->getId()) {
            return new ProjectApplyResult(ProjectApplyStatus::NotOpen, 'Je kunt je niet aanmelden voor je eigen project.');
        }
        if ($project->getVisibility() !== 'public' || $project->getDeletedAt() !== null || $project->getPublishedAt() === null
            || strcasecmp((string) $project->getStatus()?->getScope(), 'project') !== 0
            || !in_array(mb_strtolower((string) $project->getStatus()?->getName()), ['published', 'active', 'open', 'actief'], true)
            || ($project->getEndDate() !== null && $project->getEndDate() < new \DateTimeImmutable())) {
            return new ProjectApplyResult(ProjectApplyStatus::NotOpen, 'Dit project staat niet open voor aanmeldingen.');
        }
        if ($this->participants->isActiveParticipant($project, $profile)) {
            return new ProjectApplyResult(ProjectApplyStatus::AlreadyParticipant, 'Er bestaat al een actieve deelname voor dit project.');
        }
        $existing = $this->findForProfile($project, $profile, $refreshApplication);
        if ($existing !== null && ($existing->getDeletedAt() === null || $existing->getParticipant() !== null)) {
            $message = $existing->getDeletedAt() !== null ? 'Je eerdere aanvraag is ingetrokken. Deze wordt niet opnieuw ingediend.'
                : ($this->pending($existing) ? 'Je had je al aangemeld voor dit project. Status: '.$this->label($existing).'.' : 'Je eerdere aanvraag is al behandeld ('.$this->label($existing).').');
            return new ProjectApplyResult(ProjectApplyStatus::AlreadyApplied, $message, $existing);
        }
        if ($this->statuses->findFirstByScopeAndNames(ApplicationWorkflow::APPLICATION_SCOPE, ApplicationWorkflow::PENDING_NAMES) === null) {
            return new ProjectApplyResult(ProjectApplyStatus::StatusNotFound, 'De aanvraagstatus is nog niet geconfigureerd.');
        }
        return null;
    }

    public function withdraw(ProjectApplication $application, Profile $profile): bool
    {
        $result = $this->em->wrapInTransaction(function () use ($application, $profile): bool|\DomainException|AccessDeniedHttpException {
            $this->em->lock($application->getProject(), LockMode::PESSIMISTIC_WRITE);
            $this->em->lock($application, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($application);
            if ($application->getProfile()?->getId() != $profile->getId()) {
                return new AccessDeniedHttpException();
            }
            if ($application->getDeletedAt() !== null) {
                return false;
            }
            if (!$this->pending($application) || $application->getParticipant() !== null) {
                return new \DomainException('Alleen een ingediende aanvraag kan worden ingetrokken.');
            }
            $application->softDelete();
            return true;
        });
        if ($result instanceof \Throwable) {
            throw $result;
        }
        return $result;
    }

    public function pending(ProjectApplication $application): bool
    {
        return ApplicationWorkflow::matches($application->getStatus(), ApplicationWorkflow::PENDING_NAMES);
    }

    public function label(ProjectApplication $application): string
    {
        if ($application->getDeletedAt() !== null) {
            return 'Ingetrokken';
        }
        if ($this->pending($application)) {
            return 'Ingediend';
        }
        return match (true) {
            ApplicationWorkflow::matches($application->getStatus(), ApplicationWorkflow::ACCEPTED_NAMES) => 'Geaccepteerd',
            ApplicationWorkflow::matches($application->getStatus(), ApplicationWorkflow::REJECTED_NAMES) => 'Afgewezen',
            default => 'Onbekend',
        };
    }
}
