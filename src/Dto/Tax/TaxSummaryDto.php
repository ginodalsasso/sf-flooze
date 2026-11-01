<?php

declare(strict_types=1);

namespace App\Dto\Tax;

use App\Enum\CurrencyEnum;

/**
 * A fiscal year, broken down by catégorie de revenus.
 *
 * There is deliberately no global "taxable result": French law computes a base per category,
 * so a single figure subtracting every charge from every income would be meaningless. The
 * totals below are sums of comparable things — incomes with incomes, charges with charges —
 * and are never meant to be subtracted from one another.
 *
 * The out-of-scope figures are part of the summary on purpose: on a declaration, the
 * dangerous number is the one that is missing, not the one that is shown.
 */
final readonly class TaxSummaryDto
{
    public function __construct(
        public int $year,
        /** @var TaxBucketDto[] ordered by income, heaviest first */
        public array $buckets,
        /** Sum of the declarable income across every category. Informational, never a base. */
        public string $revenusTotal,
        /** Sum of the deductible charges across every category. Informational, never a base. */
        public string $chargesTotal,
        /** Income carrying no complete fiscal scope — counted in no bucket. */
        public string $outsideScopeIncome,
        /** Expense carrying no complete fiscal scope — counted in no bucket. */
        public string $outsideScopeExpense,
        public int $outsideScopeCount,
        public CurrencyEnum $currency,
        /** @var list<int> years holding movements, most recent first */
        public array $availableYears,
    ) {}

    public function hasNothing(): bool
    {
        return $this->buckets === [] && $this->outsideScopeCount === 0;
    }
}
