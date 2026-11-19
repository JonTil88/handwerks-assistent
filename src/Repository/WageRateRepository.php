<?php

declare(strict_types=1);

namespace Craft\Assistant\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Craft\Assistant\Entity\WageRate;

/**
 * @extends ServiceEntityRepository<WageRate>
 */
class WageRateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WageRate::class);
    }

    public function rateForRole(string $role, \DateTimeImmutable $asOf): ?WageRate
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.role = :role')
            ->andWhere('l.validFrom <= :asOf')
            ->setParameter('role', $role)
            ->setParameter('asOf', $asOf)
            ->orderBy('l.validFrom', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
