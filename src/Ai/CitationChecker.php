<?php

declare(strict_types=1);

namespace Craft\Assistant\Ai;

/**
 * Checks that an answer only names verifiable identifiers.
 *
 * This is the technical implementation of the core promise: the model may not
 * invent anything. If an identifier appears that was not in the given context,
 * it is reported as a warning — not silently accepted.
 */
final class CitationChecker
{
    private const PATTERN = '/\b[A-Z]{1,3}-\d+-\d+\b/';

    /**
     * @param list<string> $allowedIdentifiers
     * @param list<string> $knownSources  origin statements that are no service numbers
     *
     * @return list<string> warnings
     */
    public function check(string $answer, array $allowedIdentifiers, array $knownSources = []): array
    {
        $warnings = [];

        foreach ($this->unknownIdentifiers($answer, $allowedIdentifiers, $knownSources) as $identifier) {
            $warnings[] = sprintf(
                'The answer names "%s" — this identifier is not part of the master data that was provided.',
                $identifier,
            );
        }

        return $warnings;
    }

    /**
     * @param list<string> $allowedIdentifiers
     * @param list<string> $knownSources
     *
     * @return list<string>
     */
    public function unknownIdentifiers(string $answer, array $allowedIdentifiers, array $knownSources = []): array
    {
        $allowed = array_map(static fn (string $k): string => strtoupper(trim($k)), $allowedIdentifiers);

        // Origin statements ("Supplier quotation L-2026-014") are not service
        // numbers and must not be counted as hallucinations.
        foreach ($knownSources as $source) {
            if ('' === trim($source)) {
                continue;
            }

            $answer = str_replace($source, ' ', $answer);

            // The model often abbreviates the source ("Source: L-2026-014").
            // The identifier from the origin statement is therefore treated as
            // known as well — otherwise the report would be a false alarm.
            if (preg_match_all(self::PATTERN, $source, $matches)) {
                foreach ($matches[0] as $identifier) {
                    if (!\in_array(strtoupper($identifier), $allowed, true)) {
                        $answer = str_replace($identifier, ' ', $answer);
                    }
                }
            }
        }

        preg_match_all(self::PATTERN, $answer, $matches);

        $found = array_values(array_unique($matches[0] ?? []));

        return array_values(array_filter(
            $found,
            static fn (string $k): bool => !\in_array(strtoupper($k), $allowed, true),
        ));
    }

    /**
     * @param list<string> $allowedIdentifiers
     *
     * @return list<string> the identifiers the answer actually cites
     */
    public function usedIdentifiers(string $answer, array $allowedIdentifiers): array
    {
        preg_match_all(self::PATTERN, $answer, $matches);

        $found = array_values(array_unique($matches[0] ?? []));
        $allowed = array_map(static fn (string $k): string => strtoupper(trim($k)), $allowedIdentifiers);

        return array_values(array_filter(
            $found,
            static fn (string $k): bool => \in_array(strtoupper($k), $allowed, true),
        ));
    }
}
