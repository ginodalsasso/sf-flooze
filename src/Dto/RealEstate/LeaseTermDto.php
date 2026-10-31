<?php

declare(strict_types=1);

namespace App\Dto\RealEstate;

/**
 * The term a lease currently runs until, tacit renewals included.
 *
 * Derived from the signed end date, never stored: doing nothing is what renews a lease,
 * so there is no event to persist and no cursor to keep in sync.
 */
final readonly class LeaseTermDto
{
    public function __construct(
        /** End of the term now running — the signed date, pushed forward by each renewal. */
        public \DateTimeImmutable $endDate,
        /** How many tacit renewals have elapsed; 0 while the lease is still in its signed term. */
        public int $renewalCount,
    ) {}

    public function isRenewed(): bool
    {
        return $this->renewalCount > 0;
    }
}
