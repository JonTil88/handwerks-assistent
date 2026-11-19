<?php

declare(strict_types=1);

namespace Craft\Assistant\Domain;

/**
 * A price snapshot: amount, date it is valid from, and where it came from.
 *
 * These three attributes together make a calculation traceable: without date
 * and source, a number cannot be checked. The "stale" flag points out a price
 * that is too old to calculate with.
 */
final readonly class PriceSnapshot
{
    public function __construct(
        public Money $price,
        public \DateTimeImmutable $validFrom,
        public string $source,
        public bool $stale = false,
    ) {}

    public function origin(): string
    {
        return $this->validFrom->format('d.m.Y').' · '.$this->source;
    }

    /**
     * Origin with a note when the price is too old for a calculation.
     */
    public function originWithNote(): string
    {
        if (!$this->stale) {
            return $this->origin();
        }

        return $this->origin().' (price snapshot stale)';
    }
}
