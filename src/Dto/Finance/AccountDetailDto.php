<?php

declare(strict_types=1);

namespace App\Dto\Finance;

use App\Entity\Account;
use App\Entity\Transaction;

/**
 * View model for the account detail page.
 *
 * Groups the account, its transactions and the current month totals so the
 * controller does not have to pass several independent variables.
 */
final readonly class AccountDetailDto
{
    /**
     * @param Transaction[] $transactions
     */
    public function __construct(
        public Account $account,
        public array $transactions,
        public string $monthlyIncome,
        public string $monthlyExpense,
        public string $balance = '0.00',
        public string $investedBalance = '0.00',
        public string $availableBalance = '0.00',
    ) {}

    public function hasTransactions(): bool
    {
        return $this->transactions !== [];
    }

    public function isAssetAccount(): bool
    {
        $crypto = \App\Enum\AccountTypeEnum::CRYPTO;
        $stock = \App\Enum\AccountTypeEnum::STOCK;
        
        return \in_array($this->account->getType(), [$crypto, $stock], true);
    }

    public function monthlyNetFlow(): string
    {
        return bcsub($this->monthlyIncome, $this->monthlyExpense, 2);
    }
}
