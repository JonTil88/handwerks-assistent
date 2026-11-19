<?php

declare(strict_types=1);

namespace Craft\Assistant\Mcp\Tools;

use Craft\Assistant\Entity\AuditEntry;
use Craft\Assistant\Repository\QuotationDraftRepository;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Capability\Attribute\McpTool;

/**
 * Approves a quotation draft — a human act.
 */
final readonly class ApproveQuotationDraftTool
{
    public function __construct(
        private QuotationDraftRepository $drafts,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[McpTool(
        name: 'approve_quotation_draft',
        description: 'Approves a quotation draft. Approval is a human act and is recorded in the audit log.',
    )]
    public function __invoke(string $number, string $approvedBy = 'office'): array
    {
        $draft = $this->drafts->findByNumber(trim($number));

        if (null === $draft) {
            return ['approved' => false, 'error' => sprintf('No draft with the number "%s" found.', $number)];
        }

        $draft->approve($approvedBy);

        $this->entityManager->persist(new AuditEntry(
            'quotation_draft_approved',
            $draft->approvedBy() ?? 'office',
            'quotation_draft:'.$draft->number(),
            ['grand_total' => $draft->grandTotal()],
        ));
        $this->entityManager->flush();

        return [
            'approved' => true,
            'number' => $draft->number(),
            'approved_by' => $draft->approvedBy(),
            'approved_at' => $draft->approvedAt()?->format('Y-m-d H:i:s'),
            'grand_total' => $draft->grandTotal(),
        ];
    }
}
