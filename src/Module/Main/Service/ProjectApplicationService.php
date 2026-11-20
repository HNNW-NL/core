<?php

namespace App\Module\Main\Service;

use App\Entity\Account\Account;
use App\Entity\Account\Profile;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectApplication;
use App\Entity\Project\ProjectParticipant;
use App\Repository\Common\StatusRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class ProjectApplicationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private StatusRepository $statuses,
    ) {
    }

    public function submit(Project $project, Account $account, Profile $profile, string $motivation): ProjectApplication
    {
        $motivation = trim($motivation);
        if (mb_strlen($motivation) < 20 || mb_strlen($motivation) > 2000) {
            throw new \DomainException('De motivatie moet 20 tot en met 2.000 tekens bevatten.');
        }
        $result = $this->em->wrapInTransaction(function () use ($project, $account, $profile, $motivation): ProjectApplication|string {
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
                || !in_array(mb_strtolower((string) $project->getStatus()?->getName()), ['active', 'open', 'actief'], true)
                || ($project->getEndDate() !== null && $project->getEndDate() < new \DateTimeImmutable())) {
                return 'Dit project staat niet open voor aanmeldingen.';
            }
            if ($this->em->getRepository(ProjectParticipant::class)->findOneBy(['project' => $project, 'profile' => $profile]) !== null) {
                return 'Er bestaat al een deelname voor dit project.';
            }
            $existing = $this->em->getRepository(ProjectApplication::class)->findOneBy(['project' => $project, 'profile' => $profile]);
            if ($existing !== null) {
                return $existing;
            }
            $status = $this->statuses->findFirstByScopeAndNames('ProjectApplication', ['send', 'sent', 'pending', 'submitted', 'ingediend']);
            if ($status === null) {
                return 'De aanvraagstatus is nog niet geconfigureerd.';
            }
            $application = (new ProjectApplication())->setProject($project)->setProfile($profile)->setStatus($status)->setMotivation($motivation);
            $this->em->persist($application);
            return $application;
        });
        if (is_string($result)) {
            throw new \DomainException($result);
        }
        return $result;
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
        return strcasecmp((string) $application->getStatus()?->getScope(), 'ProjectApplication') === 0
            && in_array(mb_strtolower((string) $application->getStatus()?->getName()), ['send', 'sent', 'pending', 'submitted', 'ingediend'], true);
    }

    public function label(ProjectApplication $application): string
    {
        if ($application->getDeletedAt() !== null) {
            return 'Ingetrokken';
        }
        if ($this->pending($application)) {
            return 'Ingediend';
        }
        return match (mb_strtolower((string) $application->getStatus()?->getName())) {
            'accepted', 'geaccepteerd' => 'Geaccepteerd',
            'rejected', 'afgewezen' => 'Afgewezen',
            default => (string) $application->getStatus()?->getName(),
        };
    }
}
