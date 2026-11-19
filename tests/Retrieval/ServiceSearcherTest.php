<?php

declare(strict_types=1);

namespace Craft\Assistant\Tests\Retrieval;

use Craft\Assistant\Domain\MasterDataResolver;
use Craft\Assistant\Entity\CalculationType;
use Craft\Assistant\Entity\Service;
use Craft\Assistant\Repository\MaterialPriceRepository;
use Craft\Assistant\Repository\MaterialRepository;
use Craft\Assistant\Repository\ServiceRepository;
use Craft\Assistant\Retrieval\ServiceSearcher;
use Craft\Assistant\Retrieval\Vectorizer;
use PHPUnit\Framework\TestCase;

/**
 * The search decides what the assistant believes it knows. An empty search
 * term must not return the whole catalogue as exact matches — a score of 1.0
 * means "exact match" in this system.
 */
final class ServiceSearcherTest extends TestCase
{
    public function testEmptyTermReturnsNothing(): void
    {
        $searcher = $this->searcher();

        self::assertSame([], $searcher->search(''));
        self::assertSame([], $searcher->search('   '));
    }

    public function testWordMatchFindsTheService(): void
    {
        $searcher = $this->searcher();

        $matches = $searcher->search('Core drill');

        self::assertNotSame([], $matches);
        self::assertSame('S-5-01', $matches[0]['service']->serviceNumber());
    }

    public function testOffTopicQuestionsReturnNoMatch(): void
    {
        $searcher = $this->searcher();

        self::assertSame([], $searcher->search('Building a passive house'));
        self::assertSame([], $searcher->search('Crane with 30 tonnes lifting capacity'));
    }

    public function testCompositeFindsTheBaseService(): void
    {
        $searcher = $this->searcher();

        $matches = $searcher->search('Tiling work in the bathroom');

        self::assertNotSame([], $matches);
        self::assertSame('S-1-01', $matches[0]['service']->serviceNumber());
    }

    private function searcher(): ServiceSearcher
    {
        $services = $this->createMock(ServiceRepository::class);
        $services->method('findBy')->willReturn([
            new Service('S-1-01', 'Lay ceramic tiles', 'Sanitary', 'm²', CalculationType::Material, '42.00', '12.00', '0.800', 'Ceramic tile 30x60 white', 'tiles tiling ceramic floor wall'),
            new Service('S-5-01', 'Core drill', 'Sanitary', 'each', CalculationType::Machine, null, '12.00', '0.500', null, 'core drilling hole'),
            new Service('S-2-03', 'Supply and replace the heat exchanger', 'Heating', 'each', CalculationType::Material, '1249.00', '15.00', '4.000', 'Heat exchanger 24 kW', 'heat exchanger boiler heating'),
        ]);
        $services->method('searchSimilar')->willReturn([]);

        $vectorizer = $this->createMock(Vectorizer::class);
        $vectorizer->method('vectorize')->willReturn([0.1, 0.2, 0.3]);

        return new ServiceSearcher(
            $vectorizer,
            $services,
            new MasterDataResolver(
                $this->createMock(MaterialRepository::class),
                $this->createMock(MaterialPriceRepository::class),
            ),
        );
    }
}
