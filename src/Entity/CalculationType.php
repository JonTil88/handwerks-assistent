<?php

declare(strict_types=1);

namespace Craft\Assistant\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * How is a service line calculated?
 */
enum CalculationType: string
{
    /** Hours × wage rate (own labour) */
    case Labour = 'labour';

    /** Hours × rate of a subcontractor */
    case Subcontractor = 'subcontractor';

    /** Hours × machine rate */
    case Machine = 'machine';

    /** Quantity × material price (from the master data) */
    case Material = 'material';

    /** Quantity × disposal price */
    case Disposal = 'disposal';

    /** Fixed unit price × quantity */
    case CompositePrice = 'composite_price';

    public function requiresHours(): bool
    {
        return \in_array($this, [self::Labour, self::Subcontractor, self::Machine], true);
    }

    public function requiresQuantity(): bool
    {
        return \in_array($this, [self::Material, self::Disposal, self::CompositePrice], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Labour => 'Labour',
            self::Subcontractor => 'Subcontractor',
            self::Machine => 'Machine',
            self::Material => 'Material',
            self::Disposal => 'Disposal',
            self::CompositePrice => 'Composite price',
        };
    }
}
