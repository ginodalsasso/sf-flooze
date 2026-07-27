<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

use App\Dto\Dashboard\DashboardSummaryDto;
use App\Entity\Space;
use App\Enum\TransactionTypeEnum;
use App\Repository\AccountRepository;
use App\Repository\TransactionRepository;
use App\Service\Finance\ExchangeRateService;

class DashboardService
{
    public function __construct(
        private readonly AccountRepository $accountRepository,
        private readonly TransactionRepository $transactionRepository,
        private readonly ExchangeRateService $exchangeRateService,
    ) {}

    // Summarizes the financial state of a Space for display on the dashboard.
    public function summarize(Space $space): DashboardSummaryDto
    {
        $accounts = $this->accountRepository->findBySpace($space);

        // Accounts may hold different currencies: each balance is converted before being added.
        $totalBalance = '0.00';
        foreach ($accounts as $account) {
            $totalBalance = bcadd(
                $totalBalance,
                $this->exchangeRateService->convert($account->getBalance(), $account->getCurrency(), $space->getCurrency()),
                2,
            );
        }

        $startOfMonth = new \DateTimeImmutable('first day of this month midnight');
        $startOfNextMonth = new \DateTimeImmutable('first day of next month midnight');

        $monthlyIncome = $this->transactionRepository->sumBySpaceAndTypeAndDateRange(
            $space,
            TransactionTypeEnum::INCOME,
            $startOfMonth,
            $startOfNextMonth,
        );

        $monthlyExpense = $this->transactionRepository->sumBySpaceAndTypeAndDateRange(
            $space,
            TransactionTypeEnum::EXPENSE,
            $startOfMonth,
            $startOfNextMonth,
        );

        $netFlow = bcsub($monthlyIncome, $monthlyExpense, 2);

        return new DashboardSummaryDto(
            totalBalance: $totalBalance,
            monthlyIncome: $monthlyIncome,
            monthlyExpense: $monthlyExpense,
            netFlow: $netFlow,
            recentTransactions: $this->transactionRepository->findRecentBySpace($space, 5),
            hasAccounts: $accounts !== [],
            currency: $space->getCurrency(),
        );
    }
}
