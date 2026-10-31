<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Entity\Lease;
use App\Entity\RentPayment;
use App\Entity\Space;
use Doctrine\Persistence\ObjectRepository;

/**
 * @extends ObjectRepository<RentPayment>
 */
interface RentPaymentRepositoryInterface extends ObjectRepository
{
    /** @return RentPayment[] rent payments of the space, most recent due date first */
    public function findBySpace(Space $space): array;

    /** @return RentPayment[] rent payments of the lease, most recent due date first */
    public function findByLease(Lease $lease): array;

    /** The rent payment of the lease for this due date, if one was already recorded. */
    public function findByLeaseAndDueDate(Lease $lease, \DateTimeImmutable $dueDate): ?RentPayment;
}
