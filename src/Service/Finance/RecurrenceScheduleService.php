<?php

declare(strict_types=1);

namespace App\Service\Finance;

use App\Entity\RecurringTransaction;
use App\Enum\RecurrenceUnitEnum;
use App\Service\Finance\Contract\RecurrenceScheduleServiceInterface;

final class RecurrenceScheduleService implements RecurrenceScheduleServiceInterface
{
    /**
     * Return the date of the $index‑th occurrence of a recurring transaction.
     *
     * The calculation depends on the recurrence frequency (day or month).
     * // Daily recurrence every 2 days, starting on 2023‑01‑01
     * $date = $service->occurrenceAt($recurrence, 3); // 2023‑01‑07
     */
    public function occurrenceAt(RecurringTransaction $recurrence, int $index): \DateTimeImmutable
    {
        $frequency = $recurrence->getFrequency();
        $start = $recurrence->getStartDate()->setTime(0, 0);
        $step = $recurrence->getIntervalCount() * $frequency->step();

        return match ($frequency->unit()) {
            RecurrenceUnitEnum::DAY => $start->modify(sprintf('%+d days', $index * $step)),
            RecurrenceUnitEnum::MONTH => $this->addMonthsClamped($start, $index * $step),
        };
    }

    /**
     * Find the next occurrence after the given $date.
     * Returns null if the recurrence has an end date before the next occurrence.
     * // $date = 2023‑01‑05, recurrence starts 2023‑01‑01 daily
     * $next = $service->next($recurrence, $date); // 2023‑01‑06
     */
    public function next(RecurringTransaction $recurrence, \DateTimeImmutable $date): ?\DateTimeImmutable
    {
        $date = $date->setTime(0, 0);
        $start = $recurrence->getStartDate()->setTime(0, 0);

        if ($date < $start) {
            $candidate = $this->occurrenceAt($recurrence, 0);
        } else {
            $index = $this->indexFor($recurrence, $date);
            $candidate = $this->occurrenceAt($recurrence, $index);

            // If the candidate is not after the date, move to the next occurrence.
            if ($candidate <= $date) {
                $candidate = $this->occurrenceAt($recurrence, $index + 1);
            }
        }

        $end = $recurrence->getEndDate()?->setTime(0, 0);

        return ($end !== null && $candidate > $end) ? null : $candidate;
    }

    /**
     * Return an array of occurrence dates up to $until (or up to $limit items).
     * // Get the next 5 monthly dates until 2023‑06‑01
     * $list = $service->dueOccurrences($recurrence, new \DateTimeImmutable('2023-06-01'), 5);
     */
    public function dueOccurrences(RecurringTransaction $recurrence, \DateTimeImmutable $until, int $limit = 12): array
    {
        $until = $until->setTime(0, 0);
        $end = $recurrence->getEndDate()?->setTime(0, 0);
        $dates = [];
        $current = $recurrence->getNextOccurrenceDate()->setTime(0, 0);

        while (count($dates) < $limit && $current <= $until && ($end === null || $current <= $end)) {
            $dates[] = $current;

            $next = $this->next($recurrence, $current);
            if ($next === null) {
                break;
            }

            $current = $next;
        }

        return $dates;
    }

    /**
     * Add $months months to $date, keeping the day within the target month.
     * If the original day does not exist in the target month (e.g., 31 Jan → Feb),
     * the day is clamped to the last day of that month.
     * $new = $service->addMonthsClamped(new \DateTimeImmutable('2023-01-31'), 1);
     * // $new is 2023-02-28
     */
    private function addMonthsClamped(\DateTimeImmutable $date, int $months): \DateTimeImmutable
    {
        $firstOfTargetMonth = $date->modify('first day of this month')->modify(sprintf('%+d months', $months));
        $day = min((int) $date->format('d'), (int) $firstOfTargetMonth->format('t'));

        return $firstOfTargetMonth
            ->setDate((int) $firstOfTargetMonth->format('Y'), (int) $firstOfTargetMonth->format('n'), $day)
            ->setTime(0, 0);
    }

    /**
     * Compute the zero‑based index of the occurrence that contains $date.
     * Assumes $date is on or after the start date.
     * // Daily recurrence every 2 days, start 2023‑01‑01
     * $idx = $service->indexFor($recurrence, new \DateTimeImmutable('2023-01-05'));
     * // $idx == 2 (occurrences: 01, 03, 05)
     */
    private function indexFor(RecurringTransaction $recurrence, \DateTimeImmutable $date): int
    {
        $frequency = $recurrence->getFrequency();
        $start = $recurrence->getStartDate()->setTime(0, 0);
        $date = $date->setTime(0, 0);
        $step = $recurrence->getIntervalCount() * $frequency->step();

        $difference = match ($frequency->unit()) {
            RecurrenceUnitEnum::DAY => (int) $start->diff($date)->days,
            RecurrenceUnitEnum::MONTH => (
                (int) $date->format('Y') * 12 + (int) $date->format('n')
                - ((int) $start->format('Y') * 12 + (int) $start->format('n'))
            ),
        };

        return intdiv($difference, $step);
    }
}
