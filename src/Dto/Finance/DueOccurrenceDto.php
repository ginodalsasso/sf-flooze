<?php

declare(strict_types=1);

namespace App\Dto\Finance;

use App\Entity\RecurringTransaction;

/**
 * One occurrence of a recurrence on a given date — overdue and awaiting a decision, or upcoming
 * and shown to anticipate.
 *
 * Only the oldest overdue one is actionable: the cursor is a single date, so it can only mean
 * "everything before me is handled". Treating occurrences out of order would silently drop the
 * older ones.
 */
final readonly class DueOccurrenceDto
{
    public function __construct(
        public RecurringTransaction $recurrence,
        public \DateTimeImmutable $date,
        /** Overdue occurrences waiting behind this one, capped by the schedule limit. Always 0 when upcoming. */
        public int $backlog = 0,
    ) {}
}
