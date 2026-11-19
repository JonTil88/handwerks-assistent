<?php

declare(strict_types=1);

namespace Craft\Assistant\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Craft\Assistant\Entity\Service;

/**
 * @extends ServiceEntityRepository<Service>
 */
class ServiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Service::class);
    }

    /**
     * Similarity search via pgvector (cosine distance).
     *
     * @param float[] $vector
     *
     * @return list<array{service: Service, score: float}>
     */
    public function searchSimilar(array $vector, int $limit = 8, ?string $trade = null): array
    {
        $sql = <<<'SQL'
            SELECT l.id AS id,
                   1 - (e.embedding <=> CAST(:vector AS vector)) AS score
            FROM document_embedding e
            JOIN service l ON l.id = e.document_id
            WHERE e.document_type = 'service'
              AND l.active = true
        SQL;

        $params = ['vector' => $this->vectorToSql($vector)];
        $types = ['vector' => 'string'];

        if (null !== $trade) {
            $sql .= ' AND l.trade = :trade';
            $params['trade'] = $trade;
            $types['trade'] = 'string';
        }

        $sql .= ' ORDER BY e.embedding <=> CAST(:vector2 AS vector) LIMIT '.(int) $limit;
        $params['vector2'] = $params['vector'];

        $rows = $this->getEntityManager()->getConnection()->executeQuery($sql, $params, $types)->fetchAllAssociative();

        $out = [];
        foreach ($rows as $row) {
            $service = $this->find((int) $row['id']);
            if ($service instanceof Service) {
                $out[] = ['service' => $service, 'score' => (float) $row['score']];
            }
        }

        return $out;
    }

    /**
     * @param float[] $vector
     */
    private function vectorToSql(array $vector): string
    {
        return '['.implode(',', array_map(static fn (float $v) => rtrim(rtrim(sprintf('%.8F', $v), '0'), '.'), $vector)).']';
    }
}
