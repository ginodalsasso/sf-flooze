<?php

declare(strict_types=1);

namespace App\Service\RealEstate\Contract;

use App\Dto\RealEstate\RentDueDto;
use App\Entity\Lease;

/**
 * Read-only: the rent due on a lease that has no RentPayment recorded yet.
 *
 * Nothing is persisted here; the schedule is recomputed on every read.
 */
interface LeaseRentScheduleServiceInterface
{
    public function nextDue(Lease $lease, \DateTimeImmutable $today): ?RentDueDto;
}
