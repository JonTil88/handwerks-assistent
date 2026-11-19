<?php

declare(strict_types=1);

namespace Craft\Assistant\Tests\Ai;

use Craft\Assistant\Ai\QuotationParser;
use Craft\Assistant\Domain\Calculator;
use Craft\Assistant\Domain\MasterDataResolver;
use Craft\Assistant\Entity\CalculationType;
use Craft\Assistant\Entity\Service;
use Craft\Assistant\Entity\WageRate;
use Craft\Assistant\Repository\MachineRepository;
use Craft\Assistant\Repository\MaterialPriceRepository;
use Craft\Assistant\Repository\MaterialRepository;
use Craft\Assistant\Repository\ServiceRepository;
use Craft\Assistant\Repository\SubcontractorRepository;
use Craft\Assistant\Repository\WageRateRepository;
use PHPUnit\Framework\TestCase;

/**
 * The parser turns the model's answer into quotation lines. What matters:
 * an invented service number must not disappear silently — the draft has to
 * look incomplete on purpose.
 */
final class QuotationParserTest extends TestCase
{
    public function testUnknownServiceNumberIsReported(): void
    {
        $parser = $this->parser();

        $result = $parser->parse('{"lines":[{"service_number":"S-9-99","quantity":1},{"service_number":"S-1-02","quantity":3}]}');

        self::assertCount(1, $result['lines']);
        self::assertSame('S-1-02', $result['lines'][0]->serviceNumber());
        self::assertSame(['S-9-99'], $result['unknown']);
    }

    public function testKnownNumbersProduceNoReport(): void
    {
        $parser = $this->parser();

        $result = $parser->parse('{"lines":[{"service_number":"S-1-02","quantity":3}]}');

        self::assertCount(1, $result['lines']);
        self::assertSame([], $result['unknown']);
    }

    public function testQuantityIsPassedOn(): void
    {
        $parser = $this->parser();

        $result = $parser->parse('{"lines":[{"service_number":"S-1-02","quantity":12}]}');

        self::assertSame('12.000', $result['lines'][0]->quantity());
        // 12 × 0.5 h = 6 h × 42.50 € = 255.00 €
        self::assertSame('255.00', $result['lines'][0]->labourCost());
    }

    public function testWithoutJsonTheRawTextIsEvaluated(): void
    {
        $parser = $this->parser();

        $result = $parser->parse('I would do S-1-02 and S-9-99.');

        self::assertCount(1, $result['lines']);
        self::assertSame(['S-9-99'], $result['unknown']);
    }

    private function parser(): QuotationParser
    {
        $services = $this->createMock(ServiceRepository::class);
        $services->method('findOneBy')->willReturnCallback(
            static function (array $criteria): ?Service {
                if (($criteria['serviceNumber'] ?? null) !== 'S-1-02') {
                    return null;
                }

                return new Service('S-1-02', 'Prepare and level the substrate', 'Sanitary', 'm²', CalculationType::Labour, null, '12.00', '0.500');
            },
        );

        $wageRepository = $this->createMock(WageRateRepository::class);
        $wageRepository->method('rateForRole')->willReturn(
            new WageRate('Skilled worker', '42.50', new \DateTimeImmutable('2026-01-01')),
        );

        $calculator = new Calculator(
            new MasterDataResolver(
                $this->createMock(MaterialRepository::class),
                $this->createMock(MaterialPriceRepository::class),
            ),
            $wageRepository,
            $this->createMock(SubcontractorRepository::class),
            $this->createMock(MachineRepository::class),
        );

        return new QuotationParser($services, $calculator);
    }
}
