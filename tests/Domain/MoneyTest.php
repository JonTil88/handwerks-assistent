<?php

declare(strict_types=1);

namespace Craft\Assistant\Tests\Domain;

use Craft\Assistant\Domain\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testFromEuroKeepsCentsExactly(): void
    {
        self::assertSame(2450, Money::fromEuro('24.50')->cents());
        self::assertSame(0, Money::fromEuro('0.00')->cents());
        self::assertSame(-1250, Money::fromEuro('-12.50')->cents());
    }

    public function testAdditionAndSubtraction(): void
    {
        $sum = Money::fromEuro('19.99')->plus(Money::fromEuro('0.01'));

        self::assertSame(2000, $sum->cents());
        self::assertSame('20,00', $sum->euro());
        self::assertSame('20.00', $sum->decimal());
    }

    public function testMultiplicationByFactor(): void
    {
        self::assertSame(29400, Money::fromEuro('24.50')->multiply(12)->cents());
        self::assertSame(294, Money::fromEuro('24.50')->multiply(0.12)->cents());
    }

    public function testMarkupByPercent(): void
    {
        $subtotal = Money::fromEuro('100.00');

        self::assertSame('12,00', $subtotal->multiplyByPercent(12)->euro());
    }

    /**
     * The reason for integer cents: 0.1 + 0.2 is not 0.3 in floating point.
     */
    public function testNoFloatingPointDrift(): void
    {
        $sum = Money::fromEuro('0.10')->plus(Money::fromEuro('0.20'));

        self::assertSame(30, $sum->cents());
        self::assertSame('0,30', $sum->euro());
        self::assertSame('0.30', $sum->decimal());
        self::assertNotSame(0.3, 0.1 + 0.2);
    }

    public function testFormatting(): void
    {
        self::assertSame('1.234,56', Money::fromEuro('1234.56')->euro());
        self::assertSame('1234.56', Money::fromEuro('1234.56')->decimal());
        self::assertSame('12,34 €', Money::fromEuro('12.34')->euroWithSymbol());
    }
}
