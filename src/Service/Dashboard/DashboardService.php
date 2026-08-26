<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

use App\Dto\Dashboard\DashboardSummaryDto;
use App\Entity\Space;
use App\Enum\PeriodEnum;
use App\Enum\TransactionTypeEnum;
use App\Repository\Contract\AccountRepositoryInterface;
use App\Repository\Contract\TransactionRepositoryInterface;
use App\Service\Dashboard\Contract\DashboardServiceInterface;
use App\Service\Finance\Contract\AccountBalanceServiceInterface;
use App\Service\Finance\Contract\ExchangeRateServiceInterface;
use Symfony\Component\Clock\ClockInterface;

final class DashboardService implements DashboardServiceInterface
{
    public function __construct(
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly TransactionRepositoryInterface $transactionRepository,
        private readonly AccountBalanceServiceInterface $accountBalanceService,
        private readonly ExchangeRateServiceInterface $exchangeRateService,
        private readonly ClockInterface $clock,
    ) {}

    public function summarize(Space $space): DashboardSummaryDto
    {
        $accounts = $this->accountRepository->findBySpace($space);

        // Accounts may hold different currencies: each balance is converted before being added.
        $totalBalance = '0.00';
        foreach ($accounts as $account) {
            $totalBalance = bcadd(
                $totalBalance,
                $this->exchangeRateService->convert(
                    $this->accountBalanceService->getCurrentBalance($account),
                    $account->getCurrency(),
                    $space->getCurrency()),
                2,
            );
        }

        $month = PeriodEnum::THIS_MONTH->range($this->clock->now());

        $monthlyIncome = $this->transactionRepository->sumBySpaceAndTypeAndDateRange(
            $space,
            TransactionTypeEnum::INCOME,
            $month,
        );

        $monthlyExpense = $this->transactionRepository->sumBySpaceAndTypeAndDateRange(
            $space,
            TransactionTypeEnum::EXPENSE,
            $month,
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
