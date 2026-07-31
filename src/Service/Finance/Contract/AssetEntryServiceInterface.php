<?php

declare(strict_types=1);

namespace App\Service\Finance\Contract;

use App\Dto\Finance\AssetEntryInputDto;
use App\Entity\Asset;
use App\Entity\AssetEntry;

/** Buy/sell/dividend ledger and realized P&L (FIFO). */
interface AssetEntryServiceInterface
{
    /**
     * Record a buy, sell or dividend entry.
     *
     * @throws \InvalidArgumentException if a required amount/quantity/price is not positive
     * @throws \RuntimeException if a sell quantity exceeds the held quantity
     */
    public function recordEntry(AssetEntryInputDto $input): AssetEntry;

    /** Soft-delete an entry and reverse its linked transaction balance effects. */
    public function delete(AssetEntry $entry): void;

    /** Realized P&L for a sell entry in space currency using FIFO, null if not a sell. */
    public function calculateRealizedPnL(AssetEntry $sellEntry): ?string;

    public function calculateTotalRealizedPnL(Asset $asset): string;
}
