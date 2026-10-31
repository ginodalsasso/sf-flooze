<?php

declare(strict_types=1);

namespace App\Service\Date;

use App\Service\Date\Contract\DateShifterInterface;

final class DateShifter implements DateShifterInterface
{
    public function addMonths(\DateTimeImmutable $date, int $months): \DateTimeImmutable
    {
        // Stepping from the 1st first: the target month is chosen before the day is placed,
        // so the month can never roll over on its own.
        $firstOfTargetMonth = $date->modify('first day of this month')->modify(sprintf('%+d months', $months));
        $day = min((int) $date->format('d'), (int) $firstOfTargetMonth->format('t'));

        return $firstOfTargetMonth
            ->setDate(
                (int) $firstOfTargetMonth->format('Y'),
                (int) $firstOfTargetMonth->format('n'),
                $day,
            )
            ->setTime(0, 0);
    }

    public function addYears(\DateTimeImmutable $date, int $years): \DateTimeImmutable
    {
        return $this->addMonths($date, $years * 12);
    }
}
