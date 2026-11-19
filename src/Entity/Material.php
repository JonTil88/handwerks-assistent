<?php

declare(strict_types=1);

namespace Craft\Assistant\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Craft\Assistant\Repository\MaterialRepository;

/**
 * A material from the master data.
 *
 * A material carries no price of its own — the prices live in MaterialPrice,
 * each with a validity date and a source. That separation is what makes a
 * price traceable: the material is the thing, the price is a dated statement
 * about it.
 */
#[ORM\Entity(repositoryClass: MaterialRepository::class)]
#[ORM\Table(name: 'material')]
class Material
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 128)]
    private string $label;

    #[ORM\Column(length: 16)]
    private string $unit;

    #[ORM\Column(length: 64)]
    private string $category;

    /** @var Collection<int, MaterialPrice> */
    #[ORM\OneToMany(targetEntity: MaterialPrice::class, mappedBy: 'material', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['validFrom' => 'DESC'])]
    private Collection $prices;

    public function __construct(string $label, string $unit, string $category)
    {
        $this->label = $label;
        $this->unit = $unit;
        $this->category = $category;
        $this->prices = new ArrayCollection();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function unit(): string
    {
        return $this->unit;
    }

    public function category(): string
    {
        return $this->category;
    }

    /** @return Collection<int, MaterialPrice> */
    public function prices(): Collection
    {
        return $this->prices;
    }

    public function addPrice(MaterialPrice $price): void
    {
        if (!$this->prices->contains($price)) {
            $this->prices->add($price);
            $price->setMaterial($this);
        }
    }
}
