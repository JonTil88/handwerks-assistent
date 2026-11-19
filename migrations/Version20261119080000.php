<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Renames the audit column "user" to "actor".
 *
 * "user" is a reserved word in PostgreSQL and Doctrine does not quote it when
 * it builds the INSERT statement. The unquoted column list was a syntax error,
 * which aborted the whole transaction — and with it the approval of a
 * quotation draft.
 */
final class Version20261119080000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename the audit column "user" to "actor" (reserved word in PostgreSQL).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE audit_entry RENAME COLUMN "user" TO actor');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE audit_entry RENAME COLUMN actor TO "user"');
    }
}
