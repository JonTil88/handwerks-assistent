<?php

declare(strict_types=1);

namespace Craft\Assistant\Ai;

use Craft\Assistant\Domain\Calculator;
use Craft\Assistant\Entity\DraftLine;
use Craft\Assistant\Entity\Service;
use Craft\Assistant\Repository\ServiceRepository;

/**
 * Breaks the model's answer down into quotation lines.
 *
 * The parser is a pure function of text and master data: it looks up the
 * service numbers, hands them to the calculator and reports every number that
 * does not exist in the service catalogue.
 */
final readonly class QuotationParser
{
    public function __construct(
        private ServiceRepository $services,
        private Calculator $calculator,
    ) {
    }

    /**
     * @return array{lines: list<DraftLine>, unknown: list<string>}
     */
    public function parse(string $rawText): array
    {
        $rawLines = $this->rawLinesFrom($rawText);

        $lines = [];
        $unknown = [];

        foreach ($rawLines as $raw) {
            if (!\is_array($raw) || !isset($raw['service_number'])) {
                continue;
            }

            $number = strtoupper(trim((string) $raw['service_number']));
            $service = $this->services->findOneBy(['serviceNumber' => $number]);

            if (null === $service) {
                // An invented number must not disappear silently — the draft
                // would look more complete than it is.
                $unknown[] = $number;
                continue;
            }

            $options = ['quantity' => (float) ($raw['quantity'] ?? 1)];

            if (isset($raw['hours'])) {
                $options['hours'] = (float) $raw['hours'];
            }
            if (isset($raw['material']) && '' !== trim((string) $raw['material'])) {
                $options['material'] = trim((string) $raw['material']);
            }
            if (isset($raw['role']) && '' !== trim((string) $raw['role'])) {
                $options['role'] = trim((string) $raw['role']);
            }

            $line = $this->calculator->calculate($service, $options);

            if (isset($raw['note']) && '' !== trim((string) $raw['note'])) {
                $existing = $line->remark();
                $line->setRemark(null !== $existing
                    ? $existing.' | '.trim((string) $raw['note'])
                    : trim((string) $raw['note']));
            }

            $lines[] = $line;
        }

        return ['lines' => $lines, 'unknown' => array_values(array_unique($unknown))];
    }

    /**
     * @return list<mixed>
     */
    private function rawLinesFrom(string $rawText): array
    {
        $json = $this->extractJson($rawText);

        if (null !== $json && isset($json['lines']) && \is_array($json['lines'])) {
            return array_values($json['lines']);
        }

        // Fallback: pick up the service numbers from the raw text.
        preg_match_all('/\b[A-Z]{1,3}-\d+-\d+\b/', $rawText, $matches);

        $lines = [];
        foreach (array_unique($matches[0] ?? []) as $number) {
            $lines[] = ['service_number' => $number, 'quantity' => 1];
        }

        return $lines;
    }

    private function extractJson(string $text): ?array
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');

        if (false === $start || false === $end || $end <= $start) {
            return null;
        }

        $json = json_decode(substr($text, $start, $end - $start + 1), true);

        return \is_array($json) ? $json : null;
    }
}
