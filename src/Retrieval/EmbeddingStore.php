<?php

declare(strict_types=1);

namespace Craft\Assistant\Retrieval;

use Doctrine\DBAL\Connection;

/**
 * Manages the vectors in the document_embedding table.
 *
 * The vectors deliberately live outside the ORM: pgvector is a PostgreSQL
 * extension without a Doctrine type, so these accesses run as native SQL.
 */
final readonly class EmbeddingStore
{
    public const TABLE = 'document_embedding';

    public function __construct(private Connection $connection)
    {
    }

    /**
     * @param float[] $vector
     */
    public function store(string $documentType, int $documentId, array $vector): void
    {
        $this->connection->executeStatement(
            sprintf(
                'INSERT INTO %s (document_type, document_id, embedding, updated_at)
                 VALUES (:type, :id, CAST(:vector AS vector), NOW())
                 ON CONFLICT (document_type, document_id)
                 DO UPDATE SET embedding = EXCLUDED.embedding, updated_at = NOW()',
                self::TABLE,
            ),
            ['type' => $documentType, 'id' => $documentId, 'vector' => $this->vectorToSql($vector)],
        );
    }

    public function delete(string $documentType, int $documentId): void
    {
        $this->connection->executeStatement(
            sprintf('DELETE FROM %s WHERE document_type = :type AND document_id = :id', self::TABLE),
            ['type' => $documentType, 'id' => $documentId],
        );
    }

    public function count(?string $documentType = null): int
    {
        if (null === $documentType) {
            return (int) $this->connection->fetchOne(sprintf('SELECT COUNT(*) FROM %s', self::TABLE));
        }

        return (int) $this->connection->fetchOne(
            sprintf('SELECT COUNT(*) FROM %s WHERE document_type = :type', self::TABLE),
            ['type' => $documentType],
        );
    }

    /**
     * Empties the whole vector index.
     *
     * Needed when the master data is set up again: the vectors are linked by
     * (document_type, document_id), and the ids are assigned anew. Without
     * clearing, a vector would point at a different text.
     */
    public function clearIndex(): void
    {
        $this->connection->executeStatement(sprintf('DELETE FROM %s', self::TABLE));
    }

    /**
     * @param float[] $vector
     */
    public function vectorToSql(array $vector): string
    {
        // pgvector expects the text form [0.1,0.2,...]
        return '['.implode(',', array_map(
            static fn (float $value): string => rtrim(rtrim(sprintf('%.8F', $value), '0'), '.'),
            $vector,
        )).']';
    }
}
