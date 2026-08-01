<?php

declare(strict_types=1);

namespace App\Service\Finance\Contract;

use App\Entity\Account;
use App\Entity\Transaction;

/**
 * Computes account balances from their transactions.
 *
 * $excludedTransaction is left out of the computation although still stored: passing the row being
 * edited reads the balance as it will be once the new values are saved.
 */
interface AccountBalanceServiceInterface
{
    public function isAssetHoldingAccount(Account $account): bool;

    /** Opening balance plus every movement recorded on the account, in its own currency. */
    public function getCurrentBalance(Account $account, ?Transaction $excludedTransaction = null): string;

    /** Amount invested in assets held by this account, in the account currency. Always 0.00 for non-asset accounts. */
    public function getInvestedBalance(Account $account): string;

    /** Funds that can be spent or transferred out. */
    public function getAvailableBalance(Account $account, ?Transaction $excludedTransaction = null): string;

    public function hasAvailableFunds(Account $account, string $amount, ?Transaction $excludedTransaction = null): bool;

    /**
     * Ensure an asset-holding account does not spend invested funds.
     *
     * @throws \InvalidArgumentException when available balance is insufficient
     */
    public function guardAvailableFunds(Account $account, string $amount, ?Transaction $excludedTransaction = null): void;
}
