<?php

declare(strict_types=1);

namespace App\Service\Finance\Contract;

use App\Entity\RecurringTransaction;

interface RecurrenceScheduleServiceInterface
{
    /**
     * Returns the date of the $index-th occurrence of a recurrence.
     *
     * $index is 0-based: 0 is the first occurrence, 1 is the second, etc.
     */
    public function occurrenceAt(RecurringTransaction $recurrence, int $index): \DateTimeImmutable;

    /**
     * Returns the next occurrence after $date, or null if there is none.
     */
    public function next(RecurringTransaction $recurrence, \DateTimeImmutable $date): ?\DateTimeImmutable;

    /**
     * Returns all occurrences due from the cursor up to and including $until, capped at $limit.
     *
     * @return list<\DateTimeImmutable>
     */
    public function dueOccurrences(RecurringTransaction $recurrence, \DateTimeImmutable $until, int $limit = 12): array;
}