<?php

namespace App\Module\Main\Service;

use App\Entity\Account\Account;
use App\Entity\Account\Profile;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectApplication;
use App\Entity\Project\ProjectParticipant;
use App\Repository\Common\StatusRepository;
use App\Module\Main\DTO\ProjectApplicationSubmission;
use App\Module\Org\Handler\ProjectParticipantUpdateRoleHandler as ApplicationWorkflow;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class ProjectApplicationService
{
    private const OWNERSHIP_DENIED = '__ownership_denied__';

    public function __construct(
        private EntityManagerInterface $em,
        private StatusRepository $statuses,
    ) {
    }

    public function submit(Project $project, Account $account, Profile $profile, string $motivation): ProjectApplication
    {
        return $this->submitWithResult($project, $account, $profile, $motivation)->application;
    }

    public function submitWithResult(Project $project, Account $account, Profile $profile, string $motivation): ProjectApplicationSubmission
    {
        $motivation = trim($motivation);
        if (mb_strlen($motivation) < 20 || mb_strlen($motivation) > 2000) {
            throw new \DomainException('De motivatie moet 20 tot en met 2.000 tekens bevatten.');
        }
        $result = $this->em->wrapInTransaction(function () use ($project, $account, $profile, $motivation): ProjectApplicationSubmission|string {
            $this->em->lock($project, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($project);
            $this->em->refresh($account);
            if ($account->getDeletedAt() !== null || $account->getEmailVerifiedAt() === null
                || $profile->getDeletedAt() !== null || $profile->getAccount()?->getId() != $account->getId()) {
                throw new AccessDeniedHttpException('Bevestig eerst je e-mailadres.');
            }
            if ($project->getOwnerAccount()?->getId() == $account->getId()) {
                return 'Je kunt je niet aanmelden voor je eigen project.';
            }
            if ($project->getDeletedAt() !== null || $project->getPublishedAt() === null
                || strcasecmp((string) $project->getStatus()?->getScope(), 'project') !== 0
                || !in_array(mb_strtolower((string) $project->getStatus()?->getName()), ['published', 'active', 'open', 'actief'], true)
                || ($project->getEndDate() !== null && $project->getEndDate() < new \DateTimeImmutable())) {
                return 'Dit project staat niet open voor aanmeldingen.';
            }
            if ($this->em->getRepository(ProjectParticipant::class)->findOneBy(['project' => $project, 'profile' => $profile]) !== null) {
                return 'Er bestaat al een deelname voor dit project.';
            }

            // Altijd vers uit de database, vergrendeld, vóór elke beslissing.
            $existing = $this->findForProfile($project, $profile);
            if ($existing !== null) {
                if ($existing->getDeletedAt() !== null) {
                    // Ingetrokken aanvraag: herstel hetzelfde record (geen tweede rij).
                    $status = $this->statuses->findFirstByScopeAndNames(ApplicationWorkflow::APPLICATION_SCOPE, ApplicationWorkflow::PENDING_NAMES);
                    if ($status === null) {
                        return 'De aanvraagstatus is nog niet geconfigureerd.';
                    }
                    $existing->resubmit($status, $motivation);

                    return new ProjectApplicationSubmission($existing, true);
                }
                if (!$this->pending($existing)) {
                    return 'Je eerdere aanvraag is al behandeld ('.$this->label($existing).').';
                }
                return new ProjectApplicationSubmission($existing, false);
            }
            $status = $this->statuses->findFirstByScopeAndNames(ApplicationWorkflow::APPLICATION_SCOPE, ApplicationWorkflow::PENDING_NAMES);
            if ($status === null) {
                return 'De aanvraagstatus is nog niet geconfigureerd.';
            }
            $application = (new ProjectApplication())->setProject($project)->setProfile($profile)->setStatus($status)->setMotivation($motivation);
            $this->em->persist($application);
            return new ProjectApplicationSubmission($application, true);
        });
        if (is_string($result)) {
            throw new \DomainException($result);
        }
        return $result;
    }

    public function withdraw(ProjectApplication $application, Profile $profile): void
    {
        $error = $this->em->wrapInTransaction(function () use ($application, $profile): ?string {
            $this->em->lock($application->getProject(), LockMode::PESSIMISTIC_WRITE);
            $this->em->lock($application, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($application);
            if ($application->getProfile()?->getId() != $profile->getId()) {
                return self::OWNERSHIP_DENIED;
            }
            if ($application->getDeletedAt() !== null) {
                return null; // al ingetrokken: idempotent
            }
            if (!$this->pending($application) || $application->getParticipant() !== null) {
                return 'Alleen een ingediende aanvraag kan worden ingetrokken.';
            }
            $application->softDelete();

            return null;
        });

        if ($error === self::OWNERSHIP_DENIED) {
            throw new AccessDeniedHttpException();
        }
        if ($error !== null) {
            throw new \DomainException($error);
        }
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

    /**
     * Laadt de aanvraag van dit profiel voor dit project opnieuw uit de database,
     * ook als Doctrine hem al beheert (HINT_REFRESH), en vergrendelt de rij.
     * Mag alleen binnen een actieve transactie worden aangeroepen.
     */
    private function findForProfile(Project $project, Profile $profile): ?ProjectApplication
    {
        return $this->em->createQuery(
            'SELECT a FROM '.ProjectApplication::class.' a WHERE a.project = :project AND a.profile = :profile'
        )
            ->setParameter('project', $project)
            ->setParameter('profile', $profile)
            ->setMaxResults(1)
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->setHint(Query::HINT_REFRESH, true)
            ->getOneOrNullResult();
    }
}