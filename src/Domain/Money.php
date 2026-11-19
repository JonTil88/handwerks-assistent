<?php

declare(strict_types=1);

namespace Craft\Assistant\Domain;

/**
 * An amount in cents (integer). Deliberately not a float: 0.1 + 0.2 is not 0.3.
 */
final readonly class Money
{
    public function __construct(private int $cents) {}

    public static function fromEuro(string|float|int $amount): self
    {
        $cents = (int) round(((float) $amount) * 100);

        return new self($cents);
    }

    public static function fromCents(int $cents): self
    {
        return new self($cents);
    }

    public function cents(): int
    {
        return $this->cents;
    }

    public function plus(self $other): self
    {
        return new self($this->cents + $other->cents);
    }

    public function subtract(self $other): self
    {
        return new self($this->cents - $other->cents);
    }

    public function multiply(int|float $factor): self
    {
        return new self((int) round($this->cents * $factor));
    }

    public function multiplyByPercent(float $percent): self
    {
        return new self((int) round($this->cents * $percent / 100));
    }

    public function euro(): string
    {
        return number_format($this->cents / 100, 2, ',', '.');
    }

    public function euroWithSymbol(): string
    {
        return $this->euro().' €';
    }

    public function decimal(): string
    {
        return number_format($this->cents / 100, 2, '.', '');
    }

    public function __toString(): string
    {
        return $this->euro();
    }
}
