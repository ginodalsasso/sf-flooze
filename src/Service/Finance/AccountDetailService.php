<?php

declare(strict_types=1);

namespace App\Service\Finance;

use App\Dto\Finance\AccountDetailDto;
use App\Entity\Account;
use App\Enum\TransactionTypeEnum;
use App\Repository\TransactionRepository;

class AccountDetailService
{
    public function __construct(
        private readonly TransactionRepository $transactionRepository,
    ) {}

    public function build(Account $account): AccountDetailDto
    {
        $startOfMonth = new \DateTimeImmutable('first day of this month midnight');
        $startOfNextMonth = new \DateTimeImmutable('first day of next month midnight');

        return new AccountDetailDto(
            account: $account,
            transactions: $this->transactionRepository->findBySpace($account->getSpace(), null, $account),
            monthlyIncome: $this->transactionRepository->sumByAccountAndTypeAndDateRange(
                $account,
                TransactionTypeEnum::INCOME,
                $startOfMonth,
                $startOfNextMonth,
            ),
            monthlyExpense: $this->transactionRepository->sumByAccountAndTypeAndDateRange(
                $account,
                TransactionTypeEnum::EXPENSE,
                $startOfMonth,
                $startOfNextMonth,
            ),
        );
    }
}
