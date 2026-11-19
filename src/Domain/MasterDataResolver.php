<?php

declare(strict_types=1);

namespace Craft\Assistant\Domain;

use Craft\Assistant\Entity\Material;
use Craft\Assistant\Repository\MaterialPriceRepository;
use Craft\Assistant\Repository\MaterialRepository;

/**
 * Resolves prices and rates as of a given date.
 *
 * The resolver never returns a value without an origin: if there is no valid
 * price snapshot, it returns null — not an estimate.
 */
final readonly class MasterDataResolver
{
    /**
     * Price snapshots older than this are flagged as stale. They are not
     * discarded — the business should see them — but they are marked.
     */
    public const MAX_AGE_DAYS = 365;

    public function __construct(
        private MaterialRepository $materialRepository,
        private MaterialPriceRepository $materialPriceRepository,
    ) {}

    /**
     * The price snapshot valid for a material as of the given date.
     */
    public function priceSnapshotForMaterial(Material $material, \DateTimeImmutable $asOf): ?PriceSnapshot
    {
        $price = $this->materialPriceRepository->currentPrice((int) $material->id(), $asOf);
        if (null === $price) {
            return null;
        }

        return new PriceSnapshot(
            Money::fromEuro($price->price()),
            $price->validFrom(),
            $price->source(),
            $this->isStale($price->validFrom(), $asOf),
        );
    }

    public function priceSnapshotForLabel(string $label, \DateTimeImmutable $asOf): ?PriceSnapshot
    {
        $material = $this->materialRepository->findByLabel($label);
        if (null === $material) {
            return null;
        }

        return $this->priceSnapshotForMaterial($material, $asOf);
    }

    /**
     * A price snapshot is stale when it is older than MAX_AGE_DAYS.
     */
    public function isStale(\DateTimeImmutable $validFrom, \DateTimeImmutable $asOf): bool
    {
        $age = $asOf->getTimestamp() - $validFrom->getTimestamp();

        return $age > self::MAX_AGE_DAYS * 86400;
    }
}
