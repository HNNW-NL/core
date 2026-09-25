<?php

namespace App\Repository\Account;

use App\Entity\Account\Availability;
use App\Entity\Account\Profile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Availability>
 */
class AvailabilityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Availability::class);
    }

    /**
     * @return list<Availability>
     */
    public function findActiveForProfile(Profile $profile): array
    {
        $availabilities = $this->createQueryBuilder('availability')
            ->andWhere('availability.profile = :profile')
            ->andWhere('availability.deletedAt IS NULL')
            ->setParameter('profile', $profile)
            ->orderBy('availability.validFrom', 'DESC')
            ->addOrderBy('availability.startTime', 'ASC')
            ->getQuery()
            ->getResult();

        $dayOrder = [
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
            'sunday' => 7,
        ];

        usort(
            $availabilities,
            static function (Availability $a, Availability $b) use ($dayOrder): int {
                $aDay = $dayOrder[$a->getDayOfWeek()] ?? 8;
                $bDay = $dayOrder[$b->getDayOfWeek()] ?? 8;

                if ($aDay !== $bDay) {
                    return $aDay <=> $bDay;
                }

                return $a->getStartTime() <=> $b->getStartTime();
            }
        );

        return $availabilities;
    }

    public function save(Availability $availability, bool $flush = true): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($availability);

        if ($flush) {
            $entityManager->flush();
        }
    }
}