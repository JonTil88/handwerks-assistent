<?php

declare(strict_types=1);

namespace Craft\Assistant\Mcp\Tools;

use Craft\Assistant\Retrieval\ServiceSearcher;
use Mcp\Capability\Attribute\McpTool;

/**
 * Searches the service catalogue.
 */
final readonly class FindServicesTool
{
    public function __construct(private ServiceSearcher $searcher)
    {
    }

    #[McpTool(
        name: 'find_services',
        description: 'Searches the service catalogue. Returns service number, text, unit, calculation type and — where available — the valid price snapshot including its source.',
    )]
    public function __invoke(string $term, int $limit = 8): array
    {
        $matches = $this->searcher->search($term, max(1, min($limit, 25)));

        $result = [];
        foreach ($matches as $match) {
            $service = $match['service'];
            $result[] = [
                'service_number' => $service->serviceNumber(),
                'text' => $service->text(),
                'trade' => $service->trade(),
                'unit' => $service->unit(),
                'calculation_type' => $service->calculationType()->value,
                'list_price' => $service->listPrice(),
                'markup_percent' => $service->markupPercent(),
                'hours_per_unit' => $service->hoursPerUnit(),
                'price_snapshot' => $match['priceSnapshot']?->origin(),
                'price' => null !== $match['priceSnapshot'] ? $match['priceSnapshot']->price->euro() : null,
                'relevance' => round($match['score'], 4),
            ];
        }

        return [
            'count' => \count($result),
            'matches' => $result,
        ];
    }
}
