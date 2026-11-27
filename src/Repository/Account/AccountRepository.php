<?php

namespace App\Repository\Account;

use App\Entity\Account\Account;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Account>
 */
class AccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Account::class);
    }

    public function findByUsername(string $username): ?Account
    {
        return $this->createQueryBuilder('account')
            ->leftJoin('account.profile', 'profile')
            ->addSelect('profile')
            ->leftJoin('account.setting', 'setting')
            ->addSelect('setting')
            ->andWhere('account.username = :username')
            ->andWhere('account.deletedAt IS NULL')
            ->setParameter('username', $username)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
