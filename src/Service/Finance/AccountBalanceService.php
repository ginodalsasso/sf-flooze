<?php

declare(strict_types=1);

namespace App\Service\Finance;

use App\Entity\Account;
use App\Entity\Transaction;
use App\Enum\AccountTypeEnum;
use App\Repository\Contract\AssetEntryRepositoryInterface;
use App\Repository\Contract\TransactionRepositoryInterface;
use App\Service\Finance\Contract\AccountBalanceServiceInterface;
use App\Service\Finance\Contract\ExchangeRateServiceInterface;

/**
 * Computes account balances from their transactions.
 *
 * The current balance is derived, never stored: only the opening balance lives on the
 * entity. Asset-holding accounts (CRYPTO, STOCK) split it further into an invested part,
 * derived from their linked AssetEntry rows, and an available part the user can spend.
 */
final readonly class AccountBalanceService implements AccountBalanceServiceInterface
{
    public function __construct(
        private AssetEntryRepositoryInterface $assetEntryRepository,
        private TransactionRepositoryInterface $transactionRepository,
        private ExchangeRateServiceInterface $exchangeRateService,
    ) {}

    public function getCurrentBalance(Account $account, ?Transaction $excludedTransaction = null): string
    {
        return bcadd(
            $account->getOpeningBalance(),
            $this->transactionRepository->getBalanceDelta($account, $excludedTransaction),
            2,
        );
    }

    public function isAssetHoldingAccount(Account $account): bool
    {
        return \in_array($account->getType(), [AccountTypeEnum::CRYPTO, AccountTypeEnum::STOCK], true);
    }

    /** AssetEntry amounts are in the space currency: converted back before comparison to the account balance. */
    public function getInvestedBalance(Account $account): string
    {
        if (!$this->isAssetHoldingAccount($account)) {
            return '0.00';
        }

        $investedBalanceInSpaceCurrency = $this->assetEntryRepository->getInvestedBalance($account);
        $spaceCurrency = $account->getSpace()->getCurrency();
        $accountCurrency = $account->getCurrency();

        $investedBalace = $this->exchangeRateService->convert(
            $investedBalanceInSpaceCurrency,
            $spaceCurrency,
            $accountCurrency,
        );

        return $investedBalace;
    }

    public function getAvailableBalance(Account $account, ?Transaction $excludedTransaction = null): string
    {
        return bcsub($this->getCurrentBalance($account, $excludedTransaction), $this->getInvestedBalance($account), 2);
    }

    public function hasAvailableFunds(Account $account, string $amount, ?Transaction $excludedTransaction = null): bool
    {
        return bccomp($this->getAvailableBalance($account, $excludedTransaction), $amount, 2) >= 0;
    }

    /**
     * Classic accounts (bank, cash, saving) allow negative balances by design
     * — overdraft and credit operations are valid in personal finance. Only
     * asset accounts enforce a floor because "invested" funds are not liquid.
     */
    public function guardAvailableFunds(Account $account, string $amount, ?Transaction $excludedTransaction = null): void
    {
        // Only asset-holding accounts have invested funds
        if (!$this->isAssetHoldingAccount($account)) {
            return;
        }

        if (!$this->hasAvailableFunds($account, $amount, $excludedTransaction)) {
            throw new \InvalidArgumentException(sprintf(
                'Fonds disponibles insuffisants sur %s : %s %s requis, %s %s non investis. Les fonds investis ne peuvent pas être utilisés.',
                $account->getName(),
                $amount,
                $account->getCurrency()->value,
                $this->getAvailableBalance($account, $excludedTransaction),
                $account->getCurrency()->value,
            ));
        }
    }
}
