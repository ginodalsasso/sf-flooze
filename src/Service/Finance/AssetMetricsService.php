<?php

declare(strict_types=1);

namespace App\Service\Finance;

use App\Dto\Finance\AssetMetricsDto;
use App\Entity\Asset;
use App\Repository\AssetEntryRepository;

final readonly class AssetMetricsService
{
    public function __construct(
        private AssetEntryRepository $entryRepository,
    ) {}

    public function compute(Asset $asset): AssetMetricsDto
    {
        return new AssetMetricsDto(
            totalQuantity: $this->entryRepository->getTotalQuantity($asset),
            averagePrice: $this->entryRepository->getAveragePrice($asset),
            averagePriceInSpaceCurrency: $this->entryRepository->getAveragePriceInSpaceCurrency($asset),
            totalCost: $this->entryRepository->getTotalCost($asset),
            totalCostInSpaceCurrency: $this->entryRepository->getTotalCostInSpaceCurrency($asset),
            totalDividends: $this->entryRepository->getTotalDividends($asset),
            totalFees: $this->entryRepository->getTotalFees($asset),
        );
    }

    public function getTotalQuantity(Asset $asset): string
    {
        return $this->entryRepository->getTotalQuantity($asset);
    }

    public function hasPosition(Asset $asset): bool
    {
        return (float) $this->entryRepository->getTotalQuantity($asset) > 0.0;
    }
}
