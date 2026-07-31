<?php

declare(strict_types=1);

namespace App\Service\Finance;

use App\Entity\Account;
use App\Enum\AccountTypeEnum;
use App\Repository\AssetEntryRepository;

/**
 * Computes the split between invested and available funds for an account.
 *
 * Asset-holding accounts (CRYPTO, STOCK) track both a total balance and an
 * invested balance derived from their linked AssetEntry rows. The available
 * balance is what the user can actually spend or transfer out.
 */
readonly class AccountBalanceService
{
    public function __construct(
        private AssetEntryRepository $assetEntryRepository,
        private ExchangeRateService $exchangeRateService,
    ) {}

    public function isAssetHoldingAccount(Account $account): bool
    {
        $assetTypes = [AccountTypeEnum::CRYPTO, AccountTypeEnum::STOCK];
        $assetAccountTypes = array_map(fn($type) => $type->value, $assetTypes);
        
        return \in_array($account->getType(), $assetAccountTypes, true);
    }

    /**
     * Total amount currently invested in assets held by this account, in the account
     * currency. AssetEntry stores its amounts in the space currency, so the result is
     * converted back before it can be compared to the account balance.
     * Always 0.00 for non-asset accounts.
     */
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

    /**
     * Funds that can be spent or transferred out.
     */
    public function getAvailableBalance(Account $account): string
    {
        return bcsub($account->getBalance(), $this->getInvestedBalance($account), 2);
    }

    /**
     * Whether the account has enough available funds to cover a given amount.
     */
    public function hasAvailableFunds(Account $account, string $amount): bool
    {
        return bccomp($this->getAvailableBalance($account), $amount, 2) >= 0;
    }

    /**
     * Ensure an asset-holding account does not spend invested funds.
     *
     * Classic accounts (bank, cash, saving) allow negative balances by design
     * — overdraft and credit operations are valid in personal finance. Only
     * asset accounts enforce a floor because "invested" funds are not liquid.
     *
     * @throws \InvalidArgumentException when available balance is insufficient
     */
    public function guardAvailableFunds(Account $account, string $amount): void
    {
        // Only asset-holding accounts have invested funds
        if (!$this->isAssetHoldingAccount($account)) {
            return;
        }

        if (!$this->hasAvailableFunds($account, $amount)) {
            throw new \InvalidArgumentException("Impossible d'utiliser des fonds investis. Seuls les fonds disponibles peuvent être utilisés.");
        }
    }
}
