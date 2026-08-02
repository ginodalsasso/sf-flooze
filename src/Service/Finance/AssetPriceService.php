<?php

declare(strict_types=1);

namespace App\Service\Finance;

use App\Dto\Finance\AssetPriceDto;
use App\Entity\Asset;
use App\Repository\Contract\AssetEntryRepositoryInterface;
use App\Service\Finance\Contract\AssetPriceServiceInterface;

/**
 * Prices come from the trades themselves as long as no market API is plugged in: the last
 * one recorded is the last one known, and it is dated so callers can flag it as stale.
 */
final readonly class AssetPriceService implements AssetPriceServiceInterface
{
    public function __construct(
        private AssetEntryRepositoryInterface $entryRepository,
    ) {}

    public function getCurrentPrice(Asset $asset): ?AssetPriceDto
    {
        $lastTrade = $this->entryRepository->findLatestTrade($asset);

        if ($lastTrade === null) {
            return null;
        }

        return new AssetPriceDto($lastTrade->getUnitPrice(), $lastTrade->getDate());
    }
}
