<?php

declare(strict_types=1);

namespace App\Service\Finance\Contract;

use App\Dto\Finance\AssetPriceDto;
use App\Entity\Asset;

/**
 * Sole holder of asset prices, like ExchangeRateService is for currency rates:
 * a market API plugs in behind this contract without touching a single caller.
 */
interface AssetPriceServiceInterface
{
    /**
     * Latest known price of one unit, null when no price is known at all.
     *
     * The DTO says where the price comes from: callers must never present a stale price
     * as a live one, nor block on a provider that cannot answer.
     */
    public function getCurrentPrice(Asset $asset): ?AssetPriceDto;
}
