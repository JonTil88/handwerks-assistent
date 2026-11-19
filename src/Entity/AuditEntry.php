<?php

declare(strict_types=1);

namespace Craft\Assistant\Entity;

use Doctrine\ORM\Mapping as ORM;
use Craft\Assistant\Repository\AuditEntryRepository;

/**
 * An append-only audit entry.
 *
 * Entries are never changed — who did what, when, is written once and stays.
 *
 * The column is called "actor" and not "user": "user" is a reserved word in
 * PostgreSQL, and Doctrine does not quote it when it builds the INSERT. An
 * unquoted "user" in the column list is a syntax error, which broke the whole
 * approval transaction.
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
    private string $actor;

    #[ORM\Column(length: 64)]
    private string $object;

    #[ORM\Column(type: 'json')]
    private array $details;

    public function __construct(string $action, string $actor, string $object, array $details = [])
    {
        $this->timestamp = new \DateTimeImmutable();
        $this->action = $action;
        $this->actor = $actor;
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

    public function actor(): string
    {
        return $this->actor;
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
