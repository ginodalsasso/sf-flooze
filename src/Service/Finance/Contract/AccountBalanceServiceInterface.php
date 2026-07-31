<?php

declare(strict_types=1);

namespace App\Service\Finance\Contract;

use App\Entity\Account;

/** Computes the split between invested and available funds for an account. */
interface AccountBalanceServiceInterface
{
    public function isAssetHoldingAccount(Account $account): bool;

    /** Amount invested in assets held by this account, in the account currency. Always 0.00 for non-asset accounts. */
    public function getInvestedBalance(Account $account): string;

    /** Funds that can be spent or transferred out. */
    public function getAvailableBalance(Account $account): string;

    public function hasAvailableFunds(Account $account, string $amount): bool;

    /**
     * Ensure an asset-holding account does not spend invested funds.
     *
     * @throws \InvalidArgumentException when available balance is insufficient
     */
    public function guardAvailableFunds(Account $account, string $amount): void;
}
