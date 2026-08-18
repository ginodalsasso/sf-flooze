<?php

declare(strict_types=1);

namespace App\Service\Finance;

use App\Dto\Finance\AssetPriceDto;
use App\Entity\Asset;
use App\Enum\AssetPriceSourceEnum;
use App\Enum\AssetTypeEnum;
use App\Repository\Contract\AssetEntryRepositoryInterface;
use App\Service\Finance\Contract\AssetPriceServiceInterface;
use App\Service\Finance\Contract\CryptoPriceApiClientInterface;

/**
 * Crypto prices come from the market provider, which degrades to its last known quote offline.
 * Everything else — and any crypto the provider cannot price — falls back on the trades
 * themselves: the last one recorded is the last one known, and it is dated so callers can
 * flag it as stale.
 */
final readonly class AssetPriceService implements AssetPriceServiceInterface
{
    public function __construct(
        private AssetEntryRepositoryInterface $entryRepository,
        private CryptoPriceApiClientInterface $cryptoPriceApiClient,
    ) {}

    public function getCurrentPrice(Asset $asset): ?AssetPriceDto
    {
        if ($asset->getType() === AssetTypeEnum::CRYPTO) {
            $quote = $this->cryptoPriceApiClient->fetchPrice($asset->getTicker(), $asset->getCurrency());

            if ($quote !== null) {
                return $quote;
            }
        }

        return $this->lastTradedPrice($asset);
    }

    private function lastTradedPrice(Asset $asset): ?AssetPriceDto
    {
        $lastTrade = $this->entryRepository->findLatestTrade($asset);

        if ($lastTrade === null) {
            return null;
        }

        return new AssetPriceDto($lastTrade->getUnitPrice(), $lastTrade->getDate(), AssetPriceSourceEnum::TRADE);
    }
}
