<?php

declare(strict_types=1);

namespace App\Service\Finance;

use App\Entity\Account;
use App\Enum\AccountTypeEnum;
use App\Repository\Contract\AssetEntryRepositoryInterface;
use App\Service\Finance\Contract\AccountBalanceServiceInterface;
use App\Service\Finance\Contract\ExchangeRateServiceInterface;

/**
 * Computes the split between invested and available funds for an account.
 *
 * Asset-holding accounts (CRYPTO, STOCK) track both a total balance and an
 * invested balance derived from their linked AssetEntry rows. The available
 * balance is what the user can actually spend or transfer out.
 */
final readonly class AccountBalanceService implements AccountBalanceServiceInterface
{
    public function __construct(
        private AssetEntryRepositoryInterface $assetEntryRepository,
        private ExchangeRateServiceInterface $exchangeRateService,
    ) {}

    public function isAssetHoldingAccount(Account $account): bool
    {
        $assetTypes = [AccountTypeEnum::CRYPTO, AccountTypeEnum::STOCK];
        $assetAccountTypes = array_map(fn($type) => $type->value, $assetTypes);
        
        return \in_array($account->getType(), $assetAccountTypes, true);
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

    public function getAvailableBalance(Account $account): string
    {
        return bcsub($account->getBalance(), $this->getInvestedBalance($account), 2);
    }

    public function hasAvailableFunds(Account $account, string $amount): bool
    {
        return bccomp($this->getAvailableBalance($account), $amount, 2) >= 0;
    }

    /**
     * Classic accounts (bank, cash, saving) allow negative balances by design
     * — overdraft and credit operations are valid in personal finance. Only
     * asset accounts enforce a floor because "invested" funds are not liquid.
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
