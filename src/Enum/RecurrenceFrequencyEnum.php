<?php

declare(strict_types=1);

namespace App\Enum;

enum RecurrenceFrequencyEnum: string
{
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';
    case QUARTERLY = 'quarterly';
    case YEARLY = 'yearly';

    public function label(): string
    {
        return match($this) {
            self::DAILY     => 'Quotidien',
            self::WEEKLY    => 'Hebdomadaire',
            self::MONTHLY   => 'Mensuel',
            self::QUARTERLY => 'Trimestriel',
            self::YEARLY    => 'Annuel',
        };
    }

    /** Days add up exactly, months clamp to the last day of the target month. */
    public function unit(): RecurrenceUnitEnum
    {
        return match($this) {
            self::DAILY, self::WEEKLY                       => RecurrenceUnitEnum::DAY,
            self::MONTHLY, self::QUARTERLY, self::YEARLY    => RecurrenceUnitEnum::MONTH,
        };
    }

    public function step(): int
    {
        return match($this) {
            self::DAILY     => 1,
            self::WEEKLY    => 7,
            self::MONTHLY   => 1,
            self::QUARTERLY => 3,
            self::YEARLY    => 12,
        };
    }
}
