<?php

declare(strict_types=1);

namespace Craft\Assistant\Tests\Domain;

use Craft\Assistant\Domain\Calculator;
use Craft\Assistant\Domain\MasterDataResolver;
use Craft\Assistant\Entity\CalculationType;
use Craft\Assistant\Entity\DraftLine;
use Craft\Assistant\Entity\Service;
use Craft\Assistant\Entity\WageRate;
use Craft\Assistant\Repository\MachineRepository;
use Craft\Assistant\Repository\MaterialPriceRepository;
use Craft\Assistant\Repository\MaterialRepository;
use Craft\Assistant\Repository\SubcontractorRepository;
use Craft\Assistant\Repository\WageRateRepository;
use PHPUnit\Framework\TestCase;

/**
 * The inputs of the domain logic come from outside: from an order text, from
 * JSON of an MCP client or from master data maintenance. Unusable values must
 * never lead to negative or silently wrong costs.
 */
final class CalculatorInputTest extends TestCase
{
    private const AS_OF = '2026-11-18';

    public function testNegativeQuantityIsSetToOneAndReported(): void
    {
        $line = $this->calculate($this->labourService(), ['quantity' => -5.0]);

        self::assertSame('1.000', $line->quantity());
        self::assertSame('42.50', $line->labourCost());
        self::assertSame('47.60', $line->totalNet());
        self::assertNotNull($line->remark());
        self::assertStringContainsString('unusable', (string) $line->remark());
    }

    public function testZeroQuantityIsSetToOne(): void
    {
        $line = $this->calculate($this->labourService(), ['quantity' => 0.0]);

        self::assertSame('1.000', $line->quantity());
        self::assertSame('42.50', $line->labourCost());
    }

    public function testMissingQuantityYieldsOneUnitInsteadOfNull(): void
    {
        $line = $this->calculate($this->materialService(), []);

        self::assertSame('1.000', $line->quantity());
        self::assertSame('24.50', $line->materialCost());
    }

    public function testZeroHoursFallsBackToTheHoursStoredWithTheService(): void
    {
        // Without a given value: 1 × 1.0 h (service time) × 42.50 €
        $line = $this->calculate($this->labourService(), ['hours' => 0.0]);

        self::assertSame('42.50', $line->labourCost());
    }

    public function testInfiniteQuantityIsCaught(): void
    {
        $line = $this->calculate($this->labourService(), ['quantity' => \INF]);

        self::assertSame('1.000', $line->quantity());
        self::assertSame('42.50', $line->labourCost());
    }

    public function testNoLineCanHaveNegativeCosts(): void
    {
        foreach ([-5.0, -0.01, 0.0, \INF, \NAN] as $quantity) {
            $line = $this->calculate($this->labourService(), ['quantity' => $quantity]);

            self::assertGreaterThanOrEqual(0.0, (float) $line->totalNet(), 'Negative total for quantity '.var_export($quantity, true));
            self::assertGreaterThanOrEqual(0.0, (float) $line->labourCost(), 'Negative labour cost for quantity '.var_export($quantity, true));
        }
    }

    private function labourService(): Service
    {
        return new Service('S-1-02', 'Prepare and level the substrate', 'Sanitary', 'm²', CalculationType::Labour, null, '12.00', '1.000');
    }

    private function materialService(): Service
    {
        return new Service('S-1-01', 'Lay ceramic tiles', 'Sanitary', 'm²', CalculationType::Material, '42.00', '12.00', '0.800', 'Ceramic tile 30x60 white');
    }

    /**
     * @param array<string, mixed> $options
     */
    private function calculate(Service $service, array $options): DraftLine
    {
        $material = new \Craft\Assistant\Entity\Material('Ceramic tile 30x60 white', 'm²', 'Tiles');
        $material->addPrice(new \Craft\Assistant\Entity\MaterialPrice('24.50', new \DateTimeImmutable('2026-10-01'), 'Supplier quotation L-2026-014'));

        $materialRepository = $this->createMock(MaterialRepository::class);
        $materialRepository->method('findByLabel')->willReturnCallback(
            static fn (string $label): ?\Craft\Assistant\Entity\Material => 'Ceramic tile 30x60 white' === $label ? $material : null,
        );

        $priceRepository = $this->createMock(MaterialPriceRepository::class);
        $priceRepository->method('currentPrice')->willReturnCallback(
            static function (int $materialId, \DateTimeImmutable $asOf) use ($material): ?\Craft\Assistant\Entity\MaterialPrice {
                foreach ($material->prices() as $price) {
                    if ($price->validFrom() <= $asOf) {
                        return $price;
                    }
                }

                return null;
            },
        );

        $wageRepository = $this->createMock(WageRateRepository::class);
        $wageRepository->method('rateForRole')->willReturn(
            new WageRate('Skilled worker', '42.50', new \DateTimeImmutable('2026-01-01')),
        );

        $calculator = new Calculator(
            new MasterDataResolver($materialRepository, $priceRepository),
            $wageRepository,
            $this->createMock(SubcontractorRepository::class),
            $this->createMock(MachineRepository::class),
        );

        return $calculator->calculate($service, $options, new \DateTimeImmutable(self::AS_OF));
    }
}
