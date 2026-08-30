<?php

declare(strict_types=1);

namespace App\Service\Finance\Contract;

use App\Dto\Finance\DueOccurrenceDto;
use App\Entity\RecurringTransaction;
use App\Entity\Space;
use App\Entity\Transaction;

/**
 * A recurrence states an intent, not an accounting fact: it never writes a Transaction on its
 * own. Occurrences are computed on read and materialised by an explicit confirmation.
 */
interface RecurringTransactionServiceInterface
{
    public function save(RecurringTransaction $recurrence): void;

    public function delete(RecurringTransaction $recurrence): void;

    public function toggleActive(RecurringTransaction $recurrence): void;

    /** @return DueOccurrenceDto[] one per recurrence, its oldest due occurrence, oldest first */
    public function findDueOccurrences(Space $space): array;

    /** Overdue occurrences of the whole space, backlog included. */
    public function countDueOccurrences(Space $space): int;

    /**
     * Occurrences falling after today within $days, soonest first. Nothing to decide here:
     * they are shown to anticipate, and become confirmable once their date has passed.
     *
     * @return DueOccurrenceDto[]
     */
    public function findUpcomingOccurrences(Space $space, int $days = 30, int $limit = 5): array;

    /** Materialises the occurrence of $date, then advances the cursor. $date must be the oldest due. */
    public function confirm(RecurringTransaction $recurrence, \DateTimeImmutable $date): Transaction;

    /** Advances the cursor without creating anything. $date must be the oldest due. */
    public function skip(RecurringTransaction $recurrence, \DateTimeImmutable $date): void;
}
