<?php

declare(strict_types=1);

namespace Craft\Assistant\Tests\Domain;

use Craft\Assistant\Domain\Calculator;
use Craft\Assistant\Domain\MasterDataResolver;
use Craft\Assistant\Entity\CalculationType;
use Craft\Assistant\Entity\DraftLine;
use Craft\Assistant\Entity\Material;
use Craft\Assistant\Entity\MaterialPrice;
use Craft\Assistant\Entity\Service;
use Craft\Assistant\Entity\WageRate;
use Craft\Assistant\Repository\MachineRepository;
use Craft\Assistant\Repository\MaterialPriceRepository;
use Craft\Assistant\Repository\MaterialRepository;
use Craft\Assistant\Repository\SubcontractorRepository;
use Craft\Assistant\Repository\WageRateRepository;
use PHPUnit\Framework\TestCase;

/**
 * The calculator is where the arithmetic happens — without a model. These
 * tests therefore run without a database and without AI.
 */
final class CalculatorTest extends TestCase
{
    private const AS_OF = '2026-11-18';

    public function testLabourLineCalculatesHoursAndRate(): void
    {
        $service = new Service('S-1-02', 'Prepare and level the substrate', 'Sanitary', 'm²', CalculationType::Labour, null, '12.00', '0.500');

        $calculator = $this->calculator(
            wageRates: [$this->wageRate('Skilled worker', '42.50', '2026-01-01')],
        );

        $line = $calculator->calculate($service, ['quantity' => 10.0], $this->asOf());

        // 10 m² × 0.5 h = 5 h × 42.50 € = 212.50 € + 12 % markup = 238.00 €
        self::assertSame('212.50', $line->labourCost());
        self::assertSame('238.00', $line->totalNet());
        self::assertStringContainsString('Labour Skilled worker', $line->priceSnapshot());
        self::assertNull($line->remark());
    }

    public function testMaterialLineUsesPriceSnapshotWithSource(): void
    {
        $material = new Material('Ceramic tile 30x60 white', 'm²', 'Tiles');
        $material->addPrice(new MaterialPrice('24.50', new \DateTimeImmutable('2026-10-01'), 'Supplier quotation L-2026-014'));

        $service = new Service('S-1-01', 'Lay ceramic tiles', 'Sanitary', 'm²', CalculationType::Material, '42.00', '12.00', '0.800', 'Ceramic tile 30x60 white');

        $calculator = $this->calculator(
            materials: [$material],
            materialPrices: [$material->prices()->first()],
        );

        $line = $calculator->calculate($service, ['quantity' => 12.0], $this->asOf());

        // 12 m² × 24.50 € = 294.00 € + 12 % markup = 329.28 €
        self::assertSame('294.00', $line->materialCost());
        self::assertSame('329.28', $line->totalNet());
        self::assertStringContainsString('Supplier quotation L-2026-014', $line->priceSnapshot());
        self::assertStringContainsString('01.10.2026', $line->priceSnapshot());
    }

    public function testMissingPriceSnapshotLeadsToRemarkInsteadOfEstimate(): void
    {
        $service = new Service('S-1-01', 'Lay ceramic tiles', 'Sanitary', 'm²', CalculationType::Material, '42.00', '12.00', '0.800', 'Ceramic tile 30x60 white');

        $calculator = $this->calculator();

        $line = $calculator->calculate($service, ['quantity' => 12.0], $this->asOf());

        self::assertSame('0.00', $line->materialCost());
        self::assertSame('0.00', $line->totalNet());
        self::assertNotNull($line->remark());
        self::assertStringContainsString('No valid price snapshot', (string) $line->remark());
    }

    public function testMissingWageRateLeadsToRemark(): void
    {
        $service = new Service('S-1-02', 'Prepare and level the substrate', 'Sanitary', 'm²', CalculationType::Labour, null, '12.00', '0.500');

        $calculator = $this->calculator();

        $line = $calculator->calculate($service, ['quantity' => 10.0], $this->asOf());

        self::assertSame('0.00', $line->labourCost());
        self::assertNotNull($line->remark());
        self::assertStringContainsString('No valid wage rate', (string) $line->remark());
    }

    public function testCompositePriceMultipliesQuantityAndListPrice(): void
    {
        $service = new Service('S-2-08', 'Emergency call-out heating pipe burst', 'Heating', 'hour', CalculationType::CompositePrice, '95.00', '0.00', '1.000');

        $calculator = $this->calculator();

        $line = $calculator->calculate($service, ['quantity' => 3.0], $this->asOf());

        self::assertSame('285.00', $line->materialCost());
        self::assertSame('285.00', $line->totalNet());
        self::assertStringContainsString('List price', $line->priceSnapshot());
    }

    /**
     * @param list<WageRate>        $wageRates
     * @param list<Material>        $materials
     * @param list<MaterialPrice>   $materialPrices
     */
    private function calculator(
        array $wageRates = [],
        array $materials = [],
        array $materialPrices = [],
    ): Calculator {
        $wageRepository = $this->createMock(WageRateRepository::class);
        $wageRepository->method('rateForRole')->willReturnCallback(
            static function (string $role, \DateTimeImmutable $asOf) use ($wageRates): ?WageRate {
                foreach ($wageRates as $rate) {
                    if ($rate->role() === $role && $rate->validFrom() <= $asOf) {
                        return $rate;
                    }
                }

                return null;
            },
        );

        $materialRepository = $this->createMock(MaterialRepository::class);
        $materialRepository->method('findByLabel')->willReturnCallback(
            static function (string $label) use ($materials): ?Material {
                foreach ($materials as $material) {
                    if ($material->label() === $label) {
                        return $material;
                    }
                }

                return null;
            },
        );

        $priceRepository = $this->createMock(MaterialPriceRepository::class);
        $priceRepository->method('currentPrice')->willReturnCallback(
            static function (int $materialId, \DateTimeImmutable $asOf) use ($materialPrices): ?MaterialPrice {
                $match = null;
                foreach ($materialPrices as $price) {
                    if ((int) ($price->material()?->id() ?? 0) === $materialId && $price->validFrom() <= $asOf) {
                        $match = $price;
                    }
                }

                return $match;
            },
        );

        $resolver = new MasterDataResolver($materialRepository, $priceRepository);

        return new Calculator(
            $resolver,
            $wageRepository,
            $this->createMock(SubcontractorRepository::class),
            $this->createMock(MachineRepository::class),
        );
    }

    private function wageRate(string $role, string $rate, string $validFrom): WageRate
    {
        return new WageRate($role, $rate, new \DateTimeImmutable($validFrom));
    }

    private function asOf(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::AS_OF);
    }
}
