<?php

declare(strict_types=1);

namespace Craft\Assistant\Mcp\Tools;

use Craft\Assistant\Repository\AuditEntryRepository;
use Mcp\Capability\Attribute\McpTool;

/**
 * Reads the append-only audit log.
 */
final readonly class ReadAuditLogTool
{
    public function __construct(private AuditEntryRepository $auditLog)
    {
    }

    #[McpTool(
        name: 'read_audit_log',
        description: 'Reads the last entries of the append-only audit log (who approved or changed what, and when).',
    )]
    public function __invoke(int $limit = 20): array
    {
        $entries = [];

        foreach ($this->auditLog->recentEntries(max(1, min($limit, 100))) as $entry) {
            $entries[] = [
                'timestamp' => $entry->timestamp()->format('Y-m-d H:i:s'),
                'action' => $entry->action(),
                'user' => $entry->user(),
                'object' => $entry->object(),
                'details' => $entry->details(),
            ];
        }

        return ['count' => \count($entries), 'entries' => $entries];
    }
}
