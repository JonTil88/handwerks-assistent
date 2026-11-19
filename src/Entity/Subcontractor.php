<?php

declare(strict_types=1);

namespace Craft\Assistant\Entity;

use Doctrine\ORM\Mapping as ORM;
use Craft\Assistant\Repository\SubcontractorRepository;

/**
 * A subcontractor and the rate charged for their time.
 */
#[ORM\Entity(repositoryClass: SubcontractorRepository::class)]
#[ORM\Table(name: 'subcontractor')]
class Subcontractor
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 128)]
    private string $company;

    #[ORM\Column(length: 64)]
    private string $trade;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $hourlyRate;

    #[ORM\Column(name: 'valid_from', type: 'date_immutable')]
    private \DateTimeImmutable $validFrom;

    public function __construct(string $company, string $trade, string $hourlyRate, \DateTimeImmutable $validFrom)
    {
        $this->company = $company;
        $this->trade = $trade;
        $this->hourlyRate = $hourlyRate;
        $this->validFrom = $validFrom;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function company(): string
    {
        return $this->company;
    }

    public function trade(): string
    {
        return $this->trade;
    }

    public function hourlyRate(): string
    {
        return $this->hourlyRate;
    }

    public function validFrom(): \DateTimeImmutable
    {
        return $this->validFrom;
    }
}
