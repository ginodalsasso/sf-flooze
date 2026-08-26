<?php

declare(strict_types=1);

namespace App\Enum;

use App\Dto\DateRangeDto;

/** The period presets used across the app: quick filters and monthly aggregates. */
enum PeriodEnum: string
{
    case THIS_MONTH = 'this_month';
    case LAST_MONTH = 'last_month';
    case THIS_YEAR = 'this_year';
    case LAST_12_MONTHS = 'last_12_months';

    public function label(): string
    {
        return match($this) {
            self::THIS_MONTH     => 'Ce mois',
            self::LAST_MONTH     => 'Mois dernier',
            self::THIS_YEAR      => 'Cette année',
            self::LAST_12_MONTHS => '12 derniers mois',
        };
    }

    /** $now is a parameter, not a read: the caller owns the clock, so this stays testable. */
    public function range(\DateTimeImmutable $now): DateRangeDto
    {
        return match($this) {
            self::THIS_MONTH => new DateRangeDto(
                $now->modify('first day of this month midnight'),
                $now->modify('first day of next month midnight'),
            ),
            self::LAST_MONTH => new DateRangeDto(
                $now->modify('first day of last month midnight'),
                $now->modify('first day of this month midnight'),
            ),
            self::THIS_YEAR => new DateRangeDto(
                $now->modify('first day of January this year midnight'),
                $now->modify('first day of January next year midnight'),
            ),
            self::LAST_12_MONTHS => new DateRangeDto(
                $now->modify('-12 months')->modify('midnight'),
                $now->modify('tomorrow midnight'),
            ),
        };
    }
}
