<?php

declare(strict_types=1);

namespace App\Enum;

enum TaxYearStatusEnum: string
{
    case DRAFT = 'draft';
    case FILED = 'filed';
    case PAID = 'paid';

    public function label(): string
    {
        return match($this) {
            self::DRAFT => 'Brouillon',
            self::FILED => 'Déposée',
            self::PAID => 'Payée',
        };
    }
}
