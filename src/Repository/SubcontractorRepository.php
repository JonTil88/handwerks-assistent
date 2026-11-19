<?php

declare(strict_types=1);

namespace Craft\Assistant\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Craft\Assistant\Entity\Subcontractor;

/**
 * @extends ServiceEntityRepository<Subcontractor>
 */
class SubcontractorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subcontractor::class);
    }

    public function forTrade(string $trade, \DateTimeImmutable $asOf): ?Subcontractor
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.trade = :trade')
            ->andWhere('n.validFrom <= :asOf')
            ->setParameter('trade', $trade)
            ->setParameter('asOf', $asOf)
            ->orderBy('n.validFrom', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
