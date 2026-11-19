<?php

declare(strict_types=1);

namespace Craft\Assistant\Mcp\Tools;

use Craft\Assistant\Domain\MasterDataResolver;
use Mcp\Capability\Attribute\McpTool;

/**
 * Returns the price snapshot valid for a material, with its origin.
 */
final readonly class GetPriceSnapshotTool
{
    public function __construct(private MasterDataResolver $masterData)
    {
    }

    #[McpTool(
        name: 'get_price_snapshot',
        description: 'Returns the price snapshot valid for a material as of a date, including validity date and source. Without a valid snapshot it explicitly reports "no valid price snapshot" — it never estimates a value.',
    )]
    public function __invoke(string $material, string $asOf = ''): array
    {
        $date = '' === trim($asOf) ? new \DateTimeImmutable() : new \DateTimeImmutable($asOf);
        $snapshot = $this->masterData->priceSnapshotForLabel($material, $date);

        if (null === $snapshot) {
            return [
                'material' => $material,
                'as_of' => $date->format('Y-m-d'),
                'price_snapshot_available' => false,
                'note' => 'No valid price snapshot available. No value is estimated.',
            ];
        }

        return [
            'material' => $material,
            'as_of' => $date->format('Y-m-d'),
            'price_snapshot_available' => true,
            'price' => $snapshot->price->euro(),
            'valid_from' => $snapshot->validFrom->format('Y-m-d'),
            'source' => $snapshot->source,
            'stale' => $snapshot->stale,
        ];
    }
}
