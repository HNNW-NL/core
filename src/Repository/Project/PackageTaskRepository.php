<?php

namespace App\Repository\Project;

use App\Entity\Account\Profile;
use App\Entity\Project\PackageTask;
use App\Entity\Project\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<PackageTask>
 */
class PackageTaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PackageTask::class);
    }

    public function findOneAvailableForProject(Uuid|string $taskId, Project $project): ?PackageTask
    {
        return $this->createQueryBuilder('packageTask')
            ->innerJoin('packageTask.workPackage', 'workPackage')
            ->andWhere('packageTask.id = :taskId')
            ->andWhere('workPackage.project = :project')
            ->andWhere('packageTask.deletedAt IS NULL')
            ->andWhere('packageTask.assignedProfile IS NULL')
            ->setParameter('taskId', $taskId)
            ->setParameter('project', $project)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneForProject(Uuid|string $taskId, Project $project): ?PackageTask
    {
        return $this->createQueryBuilder('packageTask')
            ->innerJoin('packageTask.workPackage', 'workPackage')
            ->andWhere('packageTask.id = :taskId')
            ->andWhere('workPackage.project = :project')
            ->andWhere('packageTask.deletedAt IS NULL')
            ->setParameter('taskId', $taskId)
            ->setParameter('project', $project)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<PackageTask>
     */
    public function findVisibleForProjectSlug(string $projectSlug): array
    {
        return $this->createQueryBuilder('packageTask')
            ->innerJoin('packageTask.workPackage', 'workPackage')
            ->innerJoin('workPackage.project', 'project')
            ->addSelect('workPackage')
            ->addSelect('project')
            ->andWhere('project.slug = :projectSlug')
            ->andWhere('project.deletedAt IS NULL')
            ->andWhere('workPackage.deletedAt IS NULL')
            ->andWhere('packageTask.deletedAt IS NULL')
            ->setParameter('projectSlug', $projectSlug)
            ->orderBy('workPackage.createdAt', 'ASC')
            ->addOrderBy('packageTask.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(PackageTask $packageTask, bool $flush = true): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($packageTask);

        if ($flush) {
            $entityManager->flush();
        }
    }

    public function isAssignedToProfile(PackageTask $packageTask, Profile $profile): bool
    {
        return $packageTask->getAssignedProfile()?->getId()->equals($profile->getId()) ?? false;
    }
}