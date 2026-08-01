<?php

declare(strict_types=1);

namespace App\Dto\Finance;

/**
 * Aggregates of a filtered transaction list, in the space currency.
 *
 * Transfers are counted but weigh in neither total: they move money between two
 * accounts of the space without creating or destroying any.
 */
final class TransactionTotalsDto
{
    public function __construct(
        public readonly int $count,
        public readonly string $income,
        public readonly string $expense,
    ) {}

    public function getNet(): string
    {
        return bcsub($this->income, $this->expense, 2);
    }
}
