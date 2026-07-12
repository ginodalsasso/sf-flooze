<?php

declare(strict_types=1);

namespace App\Dto\Dashboard;

use App\Entity\Transaction;

/**
 * Read-only view model for the dashboard KPIs and recent activity.
 *
 * It groups data from accounts and transactions so the controller and Twig
 * template do not need to know how the numbers are computed.
 */
final readonly class DashboardSummaryDto
{
    /**
     * @param Transaction[] $recentTransactions
     */
    public function __construct(
        public string $totalBalance,
        public string $monthlyIncome,
        public string $monthlyExpense,
        public string $netFlow,
        public array $recentTransactions,
        public bool $hasAccounts,
    ) {}

    public function hasRecentActivity(): bool
    {
        return $this->recentTransactions !== [];
    }
}
