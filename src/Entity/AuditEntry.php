<?php

declare(strict_types=1);

namespace Craft\Assistant\Entity;

use Doctrine\ORM\Mapping as ORM;
use Craft\Assistant\Repository\AuditEntryRepository;

/**
 * An append-only audit entry.
 *
 * Entries are never changed — who did what, when, is written once and stays.
 */
#[ORM\Entity(repositoryClass: AuditEntryRepository::class)]
#[ORM\Table(name: 'audit_entry')]
#[ORM\Index(name: 'idx_audit_entry_timestamp', columns: ['timestamp'])]
class AuditEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $timestamp;

    #[ORM\Column(length: 64)]
    private string $action;

    #[ORM\Column(length: 64)]
    private string $user;

    #[ORM\Column(length: 64)]
    private string $object;

    #[ORM\Column(type: 'json')]
    private array $details;

    public function __construct(string $action, string $user, string $object, array $details = [])
    {
        $this->timestamp = new \DateTimeImmutable();
        $this->action = $action;
        $this->user = $user;
        $this->object = $object;
        $this->details = $details;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function timestamp(): \DateTimeImmutable
    {
        return $this->timestamp;
    }

    public function action(): string
    {
        return $this->action;
    }

    public function user(): string
    {
        return $this->user;
    }

    public function object(): string
    {
        return $this->object;
    }

    public function details(): array
    {
        return $this->details;
    }
}
