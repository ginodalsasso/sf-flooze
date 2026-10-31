<?php

declare(strict_types=1);

namespace App\Service\RealEstate\Contract;

use App\Entity\Account;
use App\Entity\Category;
use App\Entity\Lease;
use App\Entity\RentPayment;

/**
 * Confirms or marks late a lease's due rent occurrence.
 *
 * A RentPayment only ever exists once a decision was made: confirming also
 * creates the linked income Transaction, marking late does not.
 */
interface RentPaymentServiceInterface
{
    /** @throws \InvalidArgumentException if the due date is not the oldest actionable occurrence or if account/category belong to another space */
    public function confirm(Lease $lease, \DateTimeImmutable $dueDate, Account $account, ?Category $category): RentPayment;

    /** @throws \InvalidArgumentException if the due date is not the oldest actionable occurrence */
    public function markLate(Lease $lease, \DateTimeImmutable $dueDate): RentPayment;
}
