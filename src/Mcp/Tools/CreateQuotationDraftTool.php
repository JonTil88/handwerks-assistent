<?php

declare(strict_types=1);

namespace Craft\Assistant\Mcp\Tools;

use Craft\Assistant\Ai\QuotationAssistant;
use Mcp\Capability\Attribute\McpTool;

/**
 * Creates a quotation draft from an order text.
 */
final readonly class CreateQuotationDraftTool
{
    public function __construct(private QuotationAssistant $assistant)
    {
    }

    #[McpTool(
        name: 'create_quotation_draft',
        description: 'Creates a quotation draft from an order text. The model assigns the order to services, the amounts are computed by the system. The draft is not approved initially.',
    )]
    public function __invoke(string $order, string $customer = 'Sample Customer Ltd', string $project = 'Sample project'): array
    {
        $answer = $this->assistant->createDraft($order, $customer, $project, 'mcp');

        return [
            'text' => $answer->text,
            'draft_id' => $answer->draftId,
            'sources' => $answer->sources,
            'warnings' => $answer->warnings,
            'approved' => false,
        ];
    }
}
