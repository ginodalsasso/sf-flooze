<?php

declare(strict_types=1);

namespace App\Enum;

/** Sens d'une catégorie dans sa catégorie fiscale : elle apporte un revenu, ou elle en retire une charge. */
enum FiscalRoleEnum: string
{
    case REVENU = 'revenu';
    case CHARGE = 'charge';

    public function label(): string
    {
        return match($this) {
            self::REVENU => 'Revenu à déclarer',
            self::CHARGE => 'Charge déductible',
        };
    }

    public function badgeVariant(): string
    {
        return match($this) {
            self::REVENU => 'positive',
            self::CHARGE => 'info',
        };
    }
}
