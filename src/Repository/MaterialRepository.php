<?php

declare(strict_types=1);

namespace Craft\Assistant\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Craft\Assistant\Entity\Material;

/**
 * @extends ServiceEntityRepository<Material>
 */
class MaterialRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Material::class);
    }

    public function findByLabel(string $label): ?Material
    {
        return $this->findOneBy(['label' => $label]);
    }

    /**
     * Materials whose label contains the search term.
     *
     * The comparison is case-insensitive: the material is called "Washout WC"
     * and the question asks for "washout WC".
     *
     * @return list<Material>
     */
    public function searchByLabel(string $term, int $limit = 10): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('LOWER(m.label) LIKE :term')
            ->setParameter('term', '%'.mb_strtolower($term).'%')
            ->orderBy('m.label', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
