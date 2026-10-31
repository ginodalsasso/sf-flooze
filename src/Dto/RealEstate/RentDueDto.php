<?php

declare(strict_types=1);

namespace App\Dto\RealEstate;

use App\Entity\Lease;

/**
 * The oldest rent still awaiting a decision, with the overdue ones queued behind it.
 *
 * Only the oldest is actionable: a single cursor date cannot express "rent 3 paid, rent 2 not".
 */
final readonly class RentDueDto
{
    public function __construct(
        public Lease $lease,
        public \DateTimeImmutable $dueDate,
        /** Rent plus charges, as a decimal string. */
        public string $amount,
        /** Undecided occurrences after this one, not yet actionable. */
        public int $backlog = 0,
    ) {}
}
