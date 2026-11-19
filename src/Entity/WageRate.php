<?php

declare(strict_types=1);

namespace Craft\Assistant\Entity;

use Doctrine\ORM\Mapping as ORM;
use Craft\Assistant\Repository\WageRateRepository;

/**
 * A wage rate for a role, valid from a given date.
 */
#[ORM\Entity(repositoryClass: WageRateRepository::class)]
#[ORM\Table(name: 'wage_rate')]
class WageRate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $role;

    /** Hourly rate in euro (two decimal places). */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $rate;

    #[ORM\Column(name: 'valid_from', type: 'date_immutable')]
    private \DateTimeImmutable $validFrom;

    public function __construct(string $role, string $rate, \DateTimeImmutable $validFrom)
    {
        $this->role = $role;
        $this->rate = $rate;
        $this->validFrom = $validFrom;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function role(): string
    {
        return $this->role;
    }

    public function rate(): string
    {
        return $this->rate;
    }

    public function validFrom(): \DateTimeImmutable
    {
        return $this->validFrom;
    }
}
