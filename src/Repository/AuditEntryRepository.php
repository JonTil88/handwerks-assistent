<?php

declare(strict_types=1);

namespace Craft\Assistant\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Craft\Assistant\Entity\AuditEntry;

/**
 * @extends ServiceEntityRepository<AuditEntry>
 */
class AuditEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditEntry::class);
    }

    /**
     * @return list<AuditEntry>
     */
    public function recentEntries(int $limit = 20): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.timestamp', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
