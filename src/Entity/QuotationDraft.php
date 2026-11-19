<?php

declare(strict_types=1);

namespace Craft\Assistant\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Craft\Assistant\Repository\QuotationDraftRepository;

/**
 * A quotation draft.
 *
 * A draft is never approved by the model: approval is a human act, and it is
 * recorded in the audit log.
 */
#[ORM\Entity(repositoryClass: QuotationDraftRepository::class)]
#[ORM\Table(name: 'quotation_draft')]
class QuotationDraft
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32, unique: true)]
    private string $number;

    #[ORM\Column(length: 128)]
    private string $customer;

    #[ORM\Column(length: 128)]
    private string $project;

    #[ORM\Column(enumType: DraftStatus::class)]
    private DraftStatus $status = DraftStatus::Draft;

    /** @var Collection<int, DraftLine> */
    #[ORM\OneToMany(targetEntity: DraftLine::class, mappedBy: 'quotationDraft', cascade: ['all'], orphanRemoval: true)]
    #[ORM\OrderBy(['serviceNumber' => 'ASC'])]
    private Collection $lines;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $grandTotal = '0.00';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $approvedAt = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $approvedBy = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    public function __construct(string $number, string $customer, string $project)
    {
        $this->number = $number;
        $this->customer = $customer;
        $this->project = $project;
        $this->lines = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function number(): string
    {
        return $this->number;
    }

    public function customer(): string
    {
        return $this->customer;
    }

    public function project(): string
    {
        return $this->project;
    }

    public function status(): DraftStatus
    {
        return $this->status;
    }

    public function approve(string $approvedBy): void
    {
        $this->status = DraftStatus::Approved;
        $this->approvedAt = new \DateTimeImmutable();
        $this->approvedBy = $approvedBy;
    }

    public function approvedAt(): ?\DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function approvedBy(): ?string
    {
        return $this->approvedBy;
    }

    public function note(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): void
    {
        $this->note = $note;
    }

    /** @return Collection<int, DraftLine> */
    public function lines(): Collection
    {
        return $this->lines;
    }

    public function addLine(DraftLine $line): void
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setQuotationDraft($this);
        }
    }

    public function grandTotal(): string
    {
        return $this->grandTotal;
    }

    public function setGrandTotal(string $value): void
    {
        $this->grandTotal = $value;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
