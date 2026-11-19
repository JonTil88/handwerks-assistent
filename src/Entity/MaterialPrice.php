<?php

declare(strict_types=1);

namespace Craft\Assistant\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * A price statement about a material.
 *
 * Three attributes make it traceable: the amount, the date it is valid from,
 * and where it came from. Without date and source, a number cannot be checked.
 */
#[ORM\Entity]
#[ORM\Table(name: 'material_price')]
#[ORM\Index(name: 'idx_material_price_material', columns: ['material_id', 'valid_from'])]
class MaterialPrice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Material::class, inversedBy: 'prices')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Material $material = null;

    /** Price per unit in euro (two decimal places). */
    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $price;

    /** The price is valid from this date on. */
    #[ORM\Column(name: 'valid_from', type: 'date_immutable')]
    private \DateTimeImmutable $validFrom;

    /** Where the price came from, e.g. "Supplier quotation L-2026-014". */
    #[ORM\Column(length: 128)]
    private string $source;

    public function __construct(string $price, \DateTimeImmutable $validFrom, string $source)
    {
        $this->price = $price;
        $this->validFrom = $validFrom;
        $this->source = $source;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function material(): ?Material
    {
        return $this->material;
    }

    public function setMaterial(?Material $material): void
    {
        $this->material = $material;
    }

    public function price(): string
    {
        return $this->price;
    }

    public function validFrom(): \DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function source(): string
    {
        return $this->source;
    }
}
