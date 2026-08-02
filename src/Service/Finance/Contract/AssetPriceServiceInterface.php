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
    /** Latest known price of one unit, null when the asset has never been traded. */
    public function getCurrentPrice(Asset $asset): ?AssetPriceDto;
}
