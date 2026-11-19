<?php

declare(strict_types=1);

namespace Craft\Assistant\Mcp\Tools;

use Craft\Assistant\Domain\Calculator;
use Craft\Assistant\Domain\Money;
use Craft\Assistant\Repository\ServiceRepository;
use Mcp\Capability\Attribute\McpTool;

/**
 * Calculates quotation lines deterministically — without a model, without
 * persisting anything.
 *
 * The input is a JSON array with one object per line:
 * [{"service_number":"S-1-01","quantity":12,"material":"Ceramic tile 30x60","hours":3,"role":"Skilled worker"}]
 */
final readonly class CalculateQuotationTool
{
    public function __construct(
        private Calculator $calculator,
        private ServiceRepository $services,
    ) {
    }

    #[McpTool(
        name: 'calculate_quotation',
        description: 'Calculates quotation lines deterministically from the master data. Expects a JSON array with one object per line: service_number, quantity, and optionally material, hours, role, subcontractor, machine. The arithmetic happens in the system, not in the model.',
    )]
    public function __invoke(string $lines_json, string $asOf = ''): array
    {
        $rawLines = json_decode($lines_json, true);
        if (!\is_array($rawLines)) {
            return ['error' => 'lines_json is not a valid JSON array.'];
        }

        $date = '' === trim($asOf) ? new \DateTimeImmutable() : new \DateTimeImmutable($asOf);
        $lines = [];
        $total = Money::fromEuro(0);
        $warnings = [];

        foreach ($rawLines as $raw) {
            if (!\is_array($raw) || !isset($raw['service_number'])) {
                $warnings[] = 'Line without a service number skipped.';
                continue;
            }

            $number = strtoupper(trim((string) $raw['service_number']));
            $service = $this->services->findOneBy(['serviceNumber' => $number]);

            if (null === $service) {
                $warnings[] = sprintf('Unknown service number "%s" — line not calculated.', $number);
                continue;
            }

            $options = ['quantity' => (float) ($raw['quantity'] ?? 1)];
            foreach (['hours', 'material', 'role', 'subcontractor', 'machine'] as $key) {
                if (isset($raw[$key]) && '' !== trim((string) $raw[$key])) {
                    $options[$key] = \in_array($key, ['hours'], true)
                        ? (float) $raw[$key]
                        : trim((string) $raw[$key]);
                }
            }

            $line = $this->calculator->calculate($service, $options, $date);
            $total = $total->plus(Money::fromEuro($line->totalNet()));

            $lines[] = [
                'service_number' => $line->serviceNumber(),
                'text' => $line->text(),
                'quantity' => $line->quantity(),
                'unit' => $line->unit(),
                'labour_cost' => $line->labourCost(),
                'material_cost' => $line->materialCost(),
                'subcontractor_cost' => $line->subcontractorCost(),
                'machine_cost' => $line->machineCost(),
                'disposal_cost' => $line->disposalCost(),
                'markup_percent' => $line->markupPercent(),
                'total_net' => $line->totalNet(),
                'price_snapshot' => $line->priceSnapshot(),
                'remark' => $line->remark(),
            ];
        }

        return [
            'as_of' => $date->format('Y-m-d'),
            'line_count' => \count($lines),
            'lines' => $lines,
            'grand_total' => $total->euro(),
            'warnings' => $warnings,
        ];
    }
}
