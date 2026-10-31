<?php

declare(strict_types=1);

namespace App\Service\Date\Contract;

/**
 * Moves a date by whole months or years without ever overflowing into the next month.
 *
 * PHP's own "+1 month" turns 31 January into 2 or 3 March, which silently shifts a
 * schedule. Every monthly or yearly step of the app goes through here instead.
 */
interface DateShifterInterface
{
    /** The day is clamped to the last day of the target month when it does not exist there. */
    public function addMonths(\DateTimeImmutable $date, int $months): \DateTimeImmutable;

    /** Whole years, with the same clamping — 29 February becomes 28 February. */
    public function addYears(\DateTimeImmutable $date, int $years): \DateTimeImmutable;
}
