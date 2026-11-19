<?php

declare(strict_types=1);

namespace Craft\Assistant\Ai;

/**
 * The result of a request: text, sources, warnings.
 */
final readonly class Answer
{
    /**
     * @param list<array{id: string, label: string, origin: string}> $sources
     * @param list<string>                                                                 $warnings
     */
    public function __construct(
        public string $text,
        public array $sources = [],
        public array $warnings = [],
        public ?int $draftId = null,
        public bool $declined = false,
    ) {
    }

    public function hasSources(): bool
    {
        return [] !== $this->sources;
    }

    public function hasWarnings(): bool
    {
        return [] !== $this->warnings;
    }
}
