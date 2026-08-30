<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Entity\RecurringTransaction;
use App\Entity\Space;
use Doctrine\Persistence\ObjectRepository;

/**
 * @extends ObjectRepository<RecurringTransaction>
 */
interface RecurringTransactionRepositoryInterface extends ObjectRepository
{
    /** @return RecurringTransaction[] not deleted, ordered by label */
    public function findBySpace(Space $space): array;

    /** @return RecurringTransaction[] active ones, oldest cursors first */
    public function findDue(Space $space, \DateTimeImmutable $date): array;
}
