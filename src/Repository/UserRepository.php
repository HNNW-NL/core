<?php
public function findAllForAdmin(): array
{
    return $this->createQueryBuilder('u')
        ->orderBy('u.id', 'DESC')
        ->getQuery()
        ->getResult();
}

public function findBySearch(?string $search): array
{
    return $this->createQueryBuilder('u')
        ->where('LOWER(u.email) LIKE LOWER(:search)')
        ->orWhere('LOWER(u.firstName) LIKE LOWER(:search)')
        ->orWhere('LOWER(u.lastName) LIKE LOWER(:search)')
        ->setParameter(
            'search',
            '%' . strtolower($search) . '%'
        )
        ->getQuery()
        ->getResult();
}