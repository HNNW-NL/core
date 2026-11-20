<?php

namespace App\Module\Main\Service;

use App\Entity\Account\Account;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectParticipant;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class ProjectParticipantsService
{
    public function __construct(private EntityManagerInterface $em, private PublicProfileService $profiles)
    {
    }

    public function load(Project $project, ?Account $viewer): array
    {
        if ($viewer === null || $viewer->getDeletedAt() !== null) {
            throw new AccessDeniedHttpException();
        }
        $query = $this->em->createQueryBuilder()->from(ProjectParticipant::class, 'participant')
            ->innerJoin('participant.profile', 'profile')->innerJoin('profile.account', 'account')
            ->innerJoin('participant.status', 'status')
            ->andWhere('participant.project = :project')->setParameter('project', $project->getId(), 'ulid')
            ->andWhere('participant.joinedAt IS NOT NULL')->andWhere('participant.leftAt IS NULL')
            ->andWhere('profile.deletedAt IS NULL')->andWhere('account.deletedAt IS NULL')
            ->andWhere('LOWER(status.scope) = :scope')->setParameter('scope', 'projectparticipant')
            ->andWhere('LOWER(status.name) IN (:active)')->setParameter('active', ['active', 'actief']);
        $owner = $project->getOwnerAccount();
        if ($viewer->getId() != $owner?->getId()) {
            $access = (clone $query)->select('COUNT(participant.id)')->andWhere('account.id = :viewer')
                ->setParameter('viewer', $viewer->getId(), 'ulid')->getQuery()->getSingleScalarResult();
            if ((int) $access === 0) {
                throw new AccessDeniedHttpException();
            }
        }
        // Toegang is gecontroleerd voordat de volledige lijst of het totaal wordt opgehaald.
        $participants = $query->select('participant', 'profile', 'account', 'status', 'role', 'setting')
            ->leftJoin('participant.role', 'role')->leftJoin('account.setting', 'setting')
            ->orderBy('profile.displayName', 'ASC')->addOrderBy('profile.id', 'ASC')->getQuery()->getResult();
        $rows = [];
        $ownerProfile = $owner?->getProfile();
        if ($ownerProfile !== null && $ownerProfile->getDeletedAt() === null && $owner->getDeletedAt() === null) {
            $rows[(string) $ownerProfile->getId()] = $this->profiles->summary($ownerProfile, $viewer)
                + ['roles' => ['Projectleider'], 'leader' => true];
        }
        foreach ($participants as $participant) {
            $profile = $participant->getProfile();
            $id = (string) $profile->getId();
            if (!isset($rows[$id])) {
                $rows[$id] = $this->profiles->summary($profile, $viewer) + ['roles' => [], 'leader' => false];
            }
            $role = $participant->getRole();
            if ($role !== null && $role->getProject()?->getId() == $project->getId()) {
                $rows[$id]['roles'][] = $role->getName();
            }
            $rows[$id]['roles'] = array_values(array_unique($rows[$id]['roles']));
        }
        $rows = array_values($rows);
        usort($rows, static fn (array $a, array $b): int => ($b['leader'] <=> $a['leader']) ?: strnatcasecmp($a['name'], $b['name']));
        return $rows;
    }
}
