<?php

namespace App\Repository\Project;

use App\Entity\Account\Profile;
use App\Entity\Project\ProjectApplication;
use App\Module\Org\Handler\ProjectParticipantUpdateRoleHandler as ApplicationWorkflow;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AccountApplicationRepository
{
    public const PAGE_SIZE = 20;

    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function pageForProfile(Profile $profile, string $filter, int $requestedPage): array
    {
        $query = $this->em->createQueryBuilder()->from(ProjectApplication::class, 'application')
            ->leftJoin('application.status', 'status')
            ->leftJoin('application.project', 'project')
            ->leftJoin('project.ownerOrganisation', 'organisation')
            ->andWhere('application.profile = :profile')->setParameter('profile', $profile->getId(), 'ulid');
        if ($filter === 'Ingetrokken') {
            $query->andWhere('application.deletedAt IS NOT NULL');
        } elseif ($filter !== '') {
            $names = match ($filter) {
                'Ingediend' => ApplicationWorkflow::PENDING_NAMES,
                'Geaccepteerd' => ApplicationWorkflow::ACCEPTED_NAMES,
                'Afgewezen' => ApplicationWorkflow::REJECTED_NAMES,
                default => [],
            };
            if ($names === []) {
                $query->andWhere('1 = 0');
            } else {
                $query->andWhere('application.deletedAt IS NULL')
                    ->andWhere('LOWER(status.scope) = :scope')->setParameter('scope', strtolower(ApplicationWorkflow::APPLICATION_SCOPE))
                    ->andWhere('LOWER(status.name) IN (:names)')->setParameter('names', $names);
            }
        }
        $total = (int) (clone $query)->select('COUNT(application.id)')->getQuery()->getSingleScalarResult();
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min($pages, max(1, $requestedPage));
        $items = $query->select('application', 'status', 'project', 'organisation')->orderBy('application.id', 'DESC')
            ->setFirstResult(($page - 1) * self::PAGE_SIZE)->setMaxResults(self::PAGE_SIZE)->getQuery()->getResult();

        return ['items' => $items, 'page' => $page, 'pages' => $pages, 'total' => $total];
    }
}
