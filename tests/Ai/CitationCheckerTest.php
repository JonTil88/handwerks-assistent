<?php

declare(strict_types=1);

namespace Craft\Assistant\Tests\Ai;

use Craft\Assistant\Ai\CitationChecker;
use PHPUnit\Framework\TestCase;

/**
 * The citation checker decides whether an answer names an unverifiable
 * identifier. False alarms are expensive: they devalue the hallucination rate,
 * which is the central promise of the project.
 */
final class CitationCheckerTest extends TestCase
{
    private CitationChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new CitationChecker();
    }

    public function testUnknownIdentifierIsReported(): void
    {
        $warnings = $this->checker->check(
            'The service S-9-99 costs 12.00 €.',
            ['S-1-01'],
            [],
        );

        self::assertCount(1, $warnings);
        self::assertStringContainsString('S-9-99', $warnings[0]);
    }

    public function testKnownIdentifierIsNotReported(): void
    {
        $warnings = $this->checker->check(
            'The service S-1-01 costs 24.50 € per m².',
            ['S-1-01'],
            [],
        );

        self::assertSame([], $warnings);
    }

    public function testSourceWithPrefixIsNoHallucination(): void
    {
        $warnings = $this->checker->check(
            'Price 24.50 €, source: Supplier quotation L-2026-014. Service: S-1-01',
            ['S-1-01'],
            ['Supplier quotation L-2026-014'],
        );

        self::assertSame([], $warnings);
    }

    public function testAbbreviatedSourceIsNoHallucinationEither(): void
    {
        // The model frequently writes the source as the bare number.
        $warnings = $this->checker->check(
            'Price 24.50 €, source: L-2026-014. Service: S-1-01',
            ['S-1-01'],
            ['Supplier quotation L-2026-014'],
        );

        self::assertSame([], $warnings, 'An abbreviated source must not produce a false alarm.');
    }

    public function testSourceContainingAServiceNumberKeepsItValid(): void
    {
        // If a genuine service number appears in an origin statement, it stays a
        // valid citation and is not removed.
        $warnings = $this->checker->check(
            'See supplier quotation S-1-01 for the service S-1-01.',
            ['S-1-01'],
            ['Supplier quotation S-1-01'],
        );

        self::assertSame([], $warnings);
    }

    public function testUsedIdentifiers(): void
    {
        $used = $this->checker->usedIdentifiers(
            'Used: S-1-01, S-4-02 and S-9-99',
            ['S-1-01', 'S-4-02'],
        );

        self::assertSame(['S-1-01', 'S-4-02'], $used);
    }
}
