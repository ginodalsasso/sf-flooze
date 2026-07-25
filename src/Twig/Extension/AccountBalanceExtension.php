<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Entity\Account;
use App\Service\Finance\AccountBalanceService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AccountBalanceExtension extends AbstractExtension
{
    public function __construct(
        private readonly AccountBalanceService $accountBalanceService,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('account_invested_balance', [$this, 'getInvestedBalance']),
            new TwigFunction('account_available_balance', [$this, 'getAvailableBalance']),
            new TwigFunction('account_is_asset', [$this, 'isAssetAccount']),
        ];
    }

    public function getInvestedBalance(Account $account): string
    {
        return $this->accountBalanceService->getInvestedBalance($account);
    }

    public function getAvailableBalance(Account $account): string
    {
        return $this->accountBalanceService->getAvailableBalance($account);
    }

    public function isAssetAccount(Account $account): bool
    {
        return $this->accountBalanceService->isAssetHoldingAccount($account);
    }
}
