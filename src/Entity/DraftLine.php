<?php

declare(strict_types=1);

namespace Craft\Assistant\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * One calculated line of a quotation draft.
 *
 * Every amount carries the origin it was derived from: the price snapshot on
 * the right, the cost breakdown below it.
 */
#[ORM\Entity]
#[ORM\Table(name: 'draft_line')]
class DraftLine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: QuotationDraft::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?QuotationDraft $quotationDraft = null;

    #[ORM\Column(length: 32)]
    private string $serviceNumber;

    #[ORM\Column(type: 'text')]
    private string $text;

    #[ORM\Column(length: 16)]
    private string $unit;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3)]
    private string $quantity = '1.000';

    #[ORM\Column(enumType: CalculationType::class)]
    private CalculationType $calculationType;

    /** Unit price used (euro). */
    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $unitPrice = '0.00';

    /** Origin of the price, e.g. "19.10.2026 · Supplier quotation L-2026-014". */
    #[ORM\Column(length: 160)]
    private string $priceSnapshot = '';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $labourCost = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $materialCost = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $subcontractorCost = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $machineCost = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $disposalCost = '0.00';

    #[ORM\Column(type: 'decimal', precision: 6, scale: 2)]
    private string $markupPercent = '0.00';

    /** Net total of the line (euro). */
    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $totalNet = '0.00';

    /** Remark when the line could not be calculated completely. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $remark = null;

    public function __construct(
        string $serviceNumber,
        string $text,
        string $unit,
        CalculationType $calculationType,
    ) {
        $this->serviceNumber = $serviceNumber;
        $this->text = $text;
        $this->unit = $unit;
        $this->calculationType = $calculationType;
    }

    public function serviceNumber(): string
    {
        return $this->serviceNumber;
    }

    public function text(): string
    {
        return $this->text;
    }

    public function unit(): string
    {
        return $this->unit;
    }

    public function quantity(): string
    {
        return $this->quantity;
    }

    public function setQuantity(string $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function calculationType(): CalculationType
    {
        return $this->calculationType;
    }

    public function unitPrice(): string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(string $unitPrice): void
    {
        $this->unitPrice = $unitPrice;
    }

    public function priceSnapshot(): string
    {
        return $this->priceSnapshot;
    }

    public function setPriceSnapshot(string $priceSnapshot): void
    {
        $this->priceSnapshot = $priceSnapshot;
    }

    public function labourCost(): string
    {
        return $this->labourCost;
    }

    public function setLabourCost(string $value): void
    {
        $this->labourCost = $value;
    }

    public function materialCost(): string
    {
        return $this->materialCost;
    }

    public function setMaterialCost(string $value): void
    {
        $this->materialCost = $value;
    }

    public function subcontractorCost(): string
    {
        return $this->subcontractorCost;
    }

    public function setSubcontractorCost(string $value): void
    {
        $this->subcontractorCost = $value;
    }

    public function machineCost(): string
    {
        return $this->machineCost;
    }

    public function setMachineCost(string $value): void
    {
        $this->machineCost = $value;
    }

    public function disposalCost(): string
    {
        return $this->disposalCost;
    }

    public function setDisposalCost(string $value): void
    {
        $this->disposalCost = $value;
    }

    public function markupPercent(): string
    {
        return $this->markupPercent;
    }

    public function setMarkupPercent(string $value): void
    {
        $this->markupPercent = $value;
    }

    public function totalNet(): string
    {
        return $this->totalNet;
    }

    public function setTotalNet(string $value): void
    {
        $this->totalNet = $value;
    }

    public function remark(): ?string
    {
        return $this->remark;
    }

    public function setRemark(?string $remark): void
    {
        $this->remark = $remark;
    }

    public function quotationDraft(): ?QuotationDraft
    {
        return $this->quotationDraft;
    }

    public function setQuotationDraft(?QuotationDraft $quotationDraft): void
    {
        $this->quotationDraft = $quotationDraft;
    }
}
