<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Dto\DateRangeDto;
use App\Dto\Finance\TransactionFilterDto;
use App\Dto\Finance\TransactionTotalsDto;
use App\Entity\Account;
use App\Entity\Space;
use App\Entity\Transaction;
use App\Enum\TransactionTypeEnum;
use Doctrine\Persistence\ObjectRepository;

/**
 * @extends ObjectRepository<Transaction>
 */
interface TransactionRepositoryInterface extends ObjectRepository
{
    /** @return Transaction[] active transactions of the space matching the filter, most recent first. */
    public function findByFilter(Space $space, TransactionFilterDto $filter): array;

    /** Count and income/expense totals of the same result set, in the space currency. */
    public function sumByFilter(Space $space, TransactionFilterDto $filter): TransactionTotalsDto;

    /**
     * Number of active transactions carrying each tag of the space, keyed by tag id.
     *
     * @return array<int, int>
     */
    public function countByTag(Space $space): array;

    /** @return Transaction[] most recent N transactions for dashboard widget. */
    public function findRecentBySpace(Space $space, int $limit = 10): array;

    /** Total amount for a transaction type within a date range, converted to the space currency. */
    public function sumBySpaceAndTypeAndDateRange(
        Space $space,
        TransactionTypeEnum $type,
        DateRangeDto $range,
    ): string;

    /** Total amount for a transaction type on a given account within a date range. */
    public function sumByAccountAndTypeAndDateRange(
        Account $account,
        TransactionTypeEnum $type,
        DateRangeDto $range,
    ): string;

    /**
     * Net movement of every active transaction touching the account, in its own currency.
     * $excludedTransaction is left out of the total although still stored.
     */
    public function getBalanceDelta(Account $account, ?Transaction $excludedTransaction = null): string;

    /** Number of active transactions moving money on the account, incoming transfers included. */
    public function countByAccount(Account $account): int;
}
