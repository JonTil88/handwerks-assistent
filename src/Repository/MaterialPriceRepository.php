<?php

declare(strict_types=1);

namespace Craft\Assistant\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Craft\Assistant\Entity\MaterialPrice;

/**
 * @extends ServiceEntityRepository<MaterialPrice>
 */
class MaterialPriceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MaterialPrice::class);
    }

    /**
     * The price valid as of the given date (the most recent one with
     * valid_from <= the date).
     */
    public function currentPrice(int $materialId, \DateTimeImmutable $asOf): ?MaterialPrice
    {
        return $this->createQueryBuilder('p')
            ->andWhere('IDENTITY(p.material) = :materialId')
            ->andWhere('p.validFrom <= :asOf')
            ->setParameter('materialId', $materialId)
            ->setParameter('asOf', $asOf)
            ->orderBy('p.validFrom', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
