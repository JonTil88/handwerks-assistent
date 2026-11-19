<?php

declare(strict_types=1);

namespace Craft\Assistant\Entity;

enum DraftStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Approved => 'Approved',
        };
    }
}
