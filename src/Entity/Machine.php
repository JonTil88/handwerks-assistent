<?php

declare(strict_types=1);

namespace Craft\Assistant\Entity;

use Doctrine\ORM\Mapping as ORM;
use Craft\Assistant\Repository\MachineRepository;

/**
 * A machine and its hourly rate.
 */
#[ORM\Entity(repositoryClass: MachineRepository::class)]
#[ORM\Table(name: 'machine')]
class Machine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 128)]
    private string $label;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $hourlyRate;

    #[ORM\Column(name: 'valid_from', type: 'date_immutable')]
    private \DateTimeImmutable $validFrom;

    public function __construct(string $label, string $hourlyRate, \DateTimeImmutable $validFrom)
    {
        $this->label = $label;
        $this->hourlyRate = $hourlyRate;
        $this->validFrom = $validFrom;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function label(): string
    {
        return $this->label;
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
