<?php

namespace App\Repository\Account;

use App\Entity\Account\Account;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Account> */
class AccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Account::class);
    }

    public function findOneByNormalisedEmail(string $email): ?Account
    {
        return $this->createQueryBuilder('account')
            ->andWhere('LOWER(account.email) = :email')
            ->setParameter('email', strtolower(trim($email)))
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
    }
}
