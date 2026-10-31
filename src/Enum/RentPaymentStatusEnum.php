<?php

declare(strict_types=1);

namespace App\Enum;

enum RentPaymentStatusEnum: string
{
    case PAID = 'paid';
    case LATE = 'late';

    public function label(): string
    {
        return match($this) {
            self::PAID => 'Encaissé',
            self::LATE => 'Impayé',
        };
    }
}
