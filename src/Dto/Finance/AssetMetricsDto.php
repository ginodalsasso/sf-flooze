<?php

declare(strict_types=1);

namespace App\Dto\Finance;

final readonly class AssetMetricsDto
{
    public function __construct(
        public string $totalQuantity,
        public ?string $averagePrice,
        public ?string $averagePriceInSpaceCurrency,
        public string $totalCost,
        public string $totalCostInSpaceCurrency,
        public string $totalDividends,
        public string $totalFees,
    ) {}

    public function hasPosition(): bool
    {
        return (float) $this->totalQuantity > 0.0;
    }
}
