<?php

declare(strict_types=1);

namespace Craft\Assistant\Entity;

use Doctrine\ORM\Mapping as ORM;
use Craft\Assistant\Repository\ServiceRepository;

/**
 * One line of the service catalogue — the work the business knows how to do.
 *
 * The keywords are not decoration: they are how a question finds its way to
 * the right line. "WC" only matches the position if someone maintained that.
 */
#[ORM\Entity(repositoryClass: ServiceRepository::class)]
#[ORM\Table(name: 'service')]
#[ORM\Index(name: 'idx_service_trade', columns: ['trade'])]
class Service
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32, unique: true)]
    private string $serviceNumber;

    #[ORM\Column(type: 'text')]
    private string $text;

    #[ORM\Column(length: 64)]
    private string $trade;

    #[ORM\Column(length: 16)]
    private string $unit;

    #[ORM\Column(enumType: CalculationType::class)]
    private CalculationType $calculationType;

    /** List price per unit in euro (two decimal places). */
    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $listPrice = null;

    /** Markup in percent, configurable per service. */
    #[ORM\Column(type: 'decimal', precision: 6, scale: 2)]
    private string $markupPercent = '0.00';

    /** Expected working time per unit (hours), for labour, subcontractor and machine. */
    #[ORM\Column(type: 'decimal', precision: 8, scale: 3)]
    private string $hoursPerUnit = '0.000';

    /** Default material of this service (requirement from the master data). */
    #[ORM\Column(length: 128, nullable: true)]
    private ?string $defaultMaterial = null;

    /** Keywords, maintained the way a catalogue is maintained in practice. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $keywords = null;

    #[ORM\Column(type: 'boolean')]
    private bool $active = true;

    public function __construct(
        string $serviceNumber,
        string $text,
        string $trade,
        string $unit,
        CalculationType $calculationType,
        ?string $listPrice = null,
        string $markupPercent = '0.00',
        string $hoursPerUnit = '0.000',
        ?string $defaultMaterial = null,
        ?string $keywords = null,
    ) {
        $this->serviceNumber = $serviceNumber;
        $this->text = $text;
        $this->trade = $trade;
        $this->unit = $unit;
        $this->calculationType = $calculationType;
        $this->listPrice = $listPrice;
        $this->markupPercent = $markupPercent;
        $this->hoursPerUnit = $hoursPerUnit;
        $this->defaultMaterial = $defaultMaterial;
        $this->keywords = $keywords;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function serviceNumber(): string
    {
        return $this->serviceNumber;
    }

    public function text(): string
    {
        return $this->text;
    }

    public function trade(): string
    {
        return $this->trade;
    }

    public function unit(): string
    {
        return $this->unit;
    }

    public function calculationType(): CalculationType
    {
        return $this->calculationType;
    }

    public function listPrice(): ?string
    {
        return $this->listPrice;
    }

    public function markupPercent(): string
    {
        return $this->markupPercent;
    }

    public function hoursPerUnit(): string
    {
        return $this->hoursPerUnit;
    }

    public function defaultMaterial(): ?string
    {
        return $this->defaultMaterial;
    }

    public function keywords(): ?string
    {
        return $this->keywords;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }
}
