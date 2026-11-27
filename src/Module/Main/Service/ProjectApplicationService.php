<?php

namespace App\Module\Main\Service;

use App\Entity\Account\Account;
use App\Entity\Account\Profile;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectApplication;
use App\Entity\Project\ProjectParticipant;
use App\Repository\Common\StatusRepository;
use App\Repository\Project\ProjectParticipantRepository;
use App\Module\Main\DTO\ProjectApplicationSubmission;
use App\Module\Main\DTO\ProjectApplyResult;
use App\Module\Main\DTO\ProjectApplyStatus;
use App\Module\Org\Handler\ProjectParticipantUpdateRoleHandler as ApplicationWorkflow;
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

    public function findForProfile(Project $project, Profile $profile): ?ProjectApplication
    {
        return $this->em->getRepository(ProjectApplication::class)->findOneBy(['project' => $project, 'profile' => $profile]);
    }

    public function submitWithResult(Project $project, Account $account, Profile $profile, string $motivation): ProjectApplicationSubmission
    {
        $motivation = trim($motivation);
        if (mb_strlen($motivation) < 20 || mb_strlen($motivation) > 2000) {
            throw new \DomainException('De motivatie moet 20 tot en met 2.000 tekens bevatten.');
        }
        $result = $this->em->wrapInTransaction(function () use ($project, $account, $profile, $motivation): ProjectApplicationSubmission|ProjectApplyResult|string {
            $this->em->lock($project, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($project);
            $this->em->refresh($account);
            $this->em->refresh($profile);
            if ($profile->getAccount()?->getId() != $account->getId()) {
                return new ProjectApplyResult(ProjectApplyStatus::NoProfile, 'Gebruik je eigen actieve profiel.');
            }
            $blocked = $this->check($project, $profile);
            if ($blocked !== null) {
                if ($blocked->status === ProjectApplyStatus::NoProfile) {
                    return $blocked;
                }
                $existing = $blocked->application;
                if ($blocked->status === ProjectApplyStatus::AlreadyApplied && $existing !== null
                    && $existing->getDeletedAt() === null && $this->pending($existing)) {
                    return new ProjectApplicationSubmission($existing, false);
                }
                return $blocked->message;
            }
            $status = $this->statuses->findFirstByScopeAndNames(ApplicationWorkflow::APPLICATION_SCOPE, ApplicationWorkflow::PENDING_NAMES);
            if ($status === null) {
                return 'De aanvraagstatus is nog niet geconfigureerd.';
            }
            $application = (new ProjectApplication())->setProject($project)->setProfile($profile)->setStatus($status)->setMotivation($motivation);
            $this->em->persist($application);
            return new ProjectApplicationSubmission($application, true);
        });
        if ($result instanceof ProjectApplyResult) {
            throw new AccessDeniedHttpException($result->message);
        }
        if (is_string($result)) {
            throw new \DomainException($result);
        }
        return $result;
    }

    public function check(Project $project, Profile $profile): ?ProjectApplyResult
    {
        $account = $profile->getAccount();
        if ($account === null || $account->getDeletedAt() !== null || $profile->getDeletedAt() !== null
            || $account->getEmailVerifiedAt() === null) {
            return new ProjectApplyResult(ProjectApplyStatus::NoProfile, 'Bevestig eerst je e-mailadres en gebruik je eigen actieve profiel.');
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
        $existing = $this->findForProfile($project, $profile);
        if ($existing !== null) {
            $message = $existing->getDeletedAt() !== null ? 'Je eerdere aanvraag is ingetrokken. Deze wordt niet opnieuw ingediend.'
                : ($this->pending($existing) ? 'Je had je al aangemeld voor dit project. Status: '.$this->label($existing).'.' : 'Je eerdere aanvraag is al behandeld ('.$this->label($existing).').');
            return new ProjectApplyResult(ProjectApplyStatus::AlreadyApplied, $message, $existing);
        }
        if ($this->statuses->findFirstByScopeAndNames(ApplicationWorkflow::APPLICATION_SCOPE, ApplicationWorkflow::PENDING_NAMES) === null) {
            return new ProjectApplyResult(ProjectApplyStatus::StatusNotFound, 'De aanvraagstatus is nog niet geconfigureerd.');
        }
        return null;
    }

    public function withdraw(ProjectApplication $application, Profile $profile): void
    {
        $this->em->wrapInTransaction(function () use ($application, $profile): void {
            $this->em->lock($application->getProject(), LockMode::PESSIMISTIC_WRITE);
            $this->em->lock($application, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($application);
            if ($application->getProfile()?->getId() != $profile->getId()) {
                throw new AccessDeniedHttpException();
            }
            if ($application->getDeletedAt() !== null) {
                return;
            }
            if (!$this->pending($application) || $application->getParticipant() !== null) {
                throw new \DomainException('Alleen een ingediende aanvraag kan worden ingetrokken.');
            }
            $application->softDelete();
        });
    }

    public function pending(ProjectApplication $application): bool
    {
        return strcasecmp((string) $application->getStatus()?->getScope(), ApplicationWorkflow::APPLICATION_SCOPE) === 0
            && in_array(mb_strtolower((string) $application->getStatus()?->getName()), ApplicationWorkflow::PENDING_NAMES, true);
    }

    public function label(ProjectApplication $application): string
    {
        if ($application->getDeletedAt() !== null) {
            return 'Ingetrokken';
        }
        if ($this->pending($application)) {
            return 'Ingediend';
        }
        $name = mb_strtolower((string) $application->getStatus()?->getName());
        return match (true) {
            in_array($name, ApplicationWorkflow::ACCEPTED_NAMES, true) => 'Geaccepteerd',
            in_array($name, ApplicationWorkflow::REJECTED_NAMES, true) => 'Afgewezen',
            default => (string) $application->getStatus()?->getName(),
        };
    }
}
