<?php

namespace App\Module\Main\Service;

use App\Entity\Account\Account;
use App\Entity\Account\Profile;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectParticipant;
use App\Security\ProjectParticipation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class ProjectParticipantsService
{
    public const PAGE_SIZE = 20;

    public function __construct(private EntityManagerInterface $em, private PublicProfileService $profiles)
    {
    }

    public function load(Project $project, ?Account $viewer, int $requestedPage = 1): array
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
            ->andWhere('LOWER(status.scope) = :scope')->setParameter('scope', ProjectParticipation::SCOPE)
            ->andWhere('LOWER(status.name) IN (:active)')->setParameter('active', ProjectParticipation::ACTIVE_NAMES);
        $owner = $project->getOwnerAccount();
        if ($viewer->getId() != $owner?->getId()) {
            $access = (clone $query)->select('COUNT(participant.id)')->andWhere('account.id = :viewer')
                ->setParameter('viewer', $viewer->getId(), 'ulid')->getQuery()->getSingleScalarResult();
            if ((int) $access === 0) {
                throw new AccessDeniedHttpException();
            }
        }
        // Paginate profiles, not memberships: several roles must still occupy one row.
        // EXISTS avoids collection fetch joins truncating either the page or its roles.
        $activeMembership = 'EXISTS (SELECT membership.id FROM '.ProjectParticipant::class.' membership'
            .' JOIN membership.status membershipStatus WHERE membership.profile = profile'
            .' AND membership.project = :project AND membership.joinedAt IS NOT NULL AND membership.leftAt IS NULL'
            .' AND LOWER(membershipStatus.scope) = :scope AND LOWER(membershipStatus.name) IN (:active))';
        $profiles = $this->em->createQueryBuilder()->from(Profile::class, 'profile')
            ->innerJoin('profile.account', 'account')->leftJoin('account.setting', 'setting')
            ->andWhere('profile.deletedAt IS NULL')->andWhere('account.deletedAt IS NULL')
            ->andWhere($activeMembership)
            ->setParameter('project', $project->getId(), 'ulid')
            ->setParameter('scope', ProjectParticipation::SCOPE)->setParameter('active', ProjectParticipation::ACTIVE_NAMES);
        $total = (int) (clone $profiles)->select('COUNT(profile.id)')->getQuery()->getSingleScalarResult();
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min($pages, max(1, $requestedPage));
        $pageProfiles = $profiles->select('profile', 'account', 'setting')
            ->addSelect('CASE WHEN account.id = :owner THEN 0 ELSE 1 END AS HIDDEN leaderOrder')
            ->setParameter('owner', $owner?->getId(), 'ulid')
            ->addSelect("LOWER(COALESCE(NULLIF(TRIM(profile.displayName), ''), 'Deelnemer')) AS HIDDEN sortName")
            ->orderBy('leaderOrder', 'ASC')->addOrderBy('sortName', 'ASC')->addOrderBy('profile.id', 'ASC')
            ->setFirstResult(($page - 1) * self::PAGE_SIZE)->setMaxResults(self::PAGE_SIZE)->getQuery()->getResult();
        $rows = [];
        foreach ($pageProfiles as $profile) {
            $leader = $profile->getAccount()->getId() == $owner?->getId();
            $rows[(string) $profile->getId()] = $this->participantSummary($profile, $viewer)
                + ['roles' => $leader ? ['Projectleider'] : [], 'leader' => $leader];
        }
        // Fetch every role only for this page's profiles, never for the whole project.
        $participants = $rows === [] ? [] : $query->select('participant', 'profile', 'account', 'status', 'role')
            ->leftJoin('participant.role', 'role')->andWhere('profile.id IN (:profileIds)')
            ->setParameter('profileIds', array_map(static fn (Profile $profile): string => $profile->getId()->toRfc4122(), $pageProfiles))
            ->orderBy('participant.id', 'ASC')->getQuery()->getResult();
        foreach ($participants as $participant) {
            $profile = $participant->getProfile();
            $id = (string) $profile->getId();
            $role = $participant->getRole();
            if ($role !== null && $role->getProject()?->getId() == $project->getId()) {
                $rows[$id]['roles'][] = $role->getName();
            }
            $rows[$id]['roles'] = array_values(array_unique($rows[$id]['roles']));
        }
        $ownerProfile = $owner?->getProfile();
        $leader = $ownerProfile !== null && $ownerProfile->getDeletedAt() === null && $owner->getDeletedAt() === null
            ? $this->participantSummary($ownerProfile, $viewer) : null;
        return ['items' => array_values($rows), 'leader' => $leader, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    private function participantSummary(Profile $profile, Account $viewer): array
    {
        if ($this->profiles->canView($profile, $viewer)) {
            return $this->profiles->summary($profile, $viewer);
        }
        // Called only after project access has been checked. Project members may see
        // the display name and project roles, never a private avatar, legal name or link.
        return ['name' => trim((string) $profile->getDisplayName()) ?: 'Deelnemer', 'username' => null, 'avatar' => null];
    }
}
