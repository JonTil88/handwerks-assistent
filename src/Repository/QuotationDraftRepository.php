<?php

declare(strict_types=1);

namespace Craft\Assistant\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Craft\Assistant\Entity\QuotationDraft;

/**
 * @extends ServiceEntityRepository<QuotationDraft>
 */
class QuotationDraftRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QuotationDraft::class);
    }

    public function nextNumber(): string
    {
        $year = (new \DateTimeImmutable())->format('Y');
        $prefix = 'Q-'.$year.'-';

        $latest = $this->createQueryBuilder('a')
            ->andWhere('a.number LIKE :prefix')
            ->setParameter('prefix', $prefix.'%')
            ->orderBy('a.number', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $counter = 1;
        if ($latest instanceof QuotationDraft) {
            $suffix = substr($latest->number(), \strlen($prefix));
            $counter = (is_numeric($suffix) ? (int) $suffix : 0) + 1;
        }

        return $prefix.str_pad((string) $counter, 3, '0', \STR_PAD_LEFT);
    }

    public function findByNumber(string $number): ?QuotationDraft
    {
        return $this->findOneBy(['number' => $number]);
    }
}
