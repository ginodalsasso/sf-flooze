<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Dto\Finance\AssetEntryFilterDto;
use App\Entity\Account;
use App\Entity\Asset;
use App\Entity\AssetEntry;
use Doctrine\Persistence\ObjectRepository;

/**
 * @extends ObjectRepository<AssetEntry>
 */
interface AssetEntryRepositoryInterface extends ObjectRepository
{
    /** @return AssetEntry[] active entries for the asset matching the filter, most recent first */
    public function findByAsset(Asset $asset, ?AssetEntryFilterDto $filter = null): array;

    /** @return AssetEntry[] active buy entries for the asset, ordered by date (FIFO) */
    public function findBuysByAsset(Asset $asset): array;

    /** Net quantity held (buy - sell), excluding soft-deleted entries. */
    public function getTotalQuantity(Asset $asset): string;

    /** Sum of dividends received for the asset. */
    public function getTotalDividends(Asset $asset): string;

    /** Sum of fees paid across all active entries for the asset. */
    public function getTotalFees(Asset $asset): string;

    /** Total cost basis in asset currency. */
    public function getTotalCost(Asset $asset): string;

    /** Total amount currently invested in assets held by this account (BUY - SELL in space currency). */
    public function getInvestedBalance(Account $account): string;

    /** Total cost basis in space currency (with historical FX). */
    public function getTotalCostInSpaceCurrency(Asset $asset): string;

    /** Weighted average purchase price in asset currency, null when nothing is held. */
    public function getAveragePrice(Asset $asset): ?string;

    /** Weighted average purchase price in space currency (with historical FX). */
    public function getAveragePriceInSpaceCurrency(Asset $asset): ?string;
}
