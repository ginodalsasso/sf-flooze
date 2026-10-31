<?php

declare(strict_types=1);

namespace App\Enum;

enum LeaseTypeEnum: string
{
    case NU = 'nu';
    case MEUBLE = 'meuble';
    case COLOCATION = 'colocation';

    public function label(): string
    {
        return match($this) {
            self::NU         => 'Location nue',
            self::MEUBLE     => 'Location meublée',
            self::COLOCATION => 'Colocation',
        };
    }

    /** Legal minimum term in years — the form uses it to suggest an end date. */
    public function defaultDurationYears(): int
    {
        return match($this) {
            self::NU                       => 3,
            self::MEUBLE, self::COLOCATION => 1,
        };
    }
}
