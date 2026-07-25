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
    ) {}

    public function isAssetHoldingAccount(Account $account): bool
    {
        return \in_array($account->getType(), [AccountTypeEnum::CRYPTO, AccountTypeEnum::STOCK], true);
    }

    /**
     * Total amount currently invested in assets held by this account.
     * Always 0.00 for non-asset accounts.
     */
    public function getInvestedBalance(Account $account): string
    {
        if (!$this->isAssetHoldingAccount($account)) {
            return '0.00';
        }

        return bcadd($this->assetEntryRepository->getInvestedBalance($account), '0', 2);
    }

    /**
     * Funds that can be spent or transferred out.
     */
    public function getAvailableBalance(Account $account): string
    {
        return bcsub($account->getBalance(), $this->getInvestedBalance($account), 2);
    }

    public function hasAvailableFunds(Account $account, string $amount): bool
    {
        return bccomp($this->getAvailableBalance($account), $amount, 2) >= 0;
    }

    /**
     * Ensure an asset-holding account does not use invested funds.
     *
     * @throws \InvalidArgumentException when available balance is insufficient
     */
    public function guardAvailableFunds(Account $account, string $amount): void
    {
        if (!$this->isAssetHoldingAccount($account)) {
            return;
        }

        if (!$this->hasAvailableFunds($account, $amount)) {
            throw new \InvalidArgumentException("Impossible d'utiliser des fonds investis. Seuls les fonds disponibles peuvent être utilisés.");
        }
    }
}
