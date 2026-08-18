<?php

declare(strict_types=1);

namespace App\Service\Finance\Contract;

use App\Dto\Finance\AssetPriceDto;
use App\Dto\Finance\CryptoCoinDto;
use App\Enum\CurrencyEnum;

/** Reads crypto spot prices and the provider's coin catalogue. */
interface CryptoPriceApiClientInterface
{
    /**
     * Last price of one unit of $ticker in $currency, or null when none was ever read.
     *
     * Never throws and never blocks on a dead provider: the returned DTO carries its own
     * source, so an offline answer is a stale price the caller can display, not a failure.
     */
    public function fetchPrice(string $ticker, CurrencyEnum $currency): ?AssetPriceDto;

    /**
     * Coins matching a free-text query, most relevant first.
     *
     * Empty when the provider cannot answer: the caller falls back on manual entry
     * rather than blocking the creation of an asset.
     *
     * @return list<CryptoCoinDto>
     */
    public function searchCoins(string $query): array;
}
