<?php

declare(strict_types=1);

namespace App\Service\Finance\Contract;

use App\Dto\Finance\AssetMetricsDto;
use App\Entity\Asset;

/** Aggregated metrics for an asset: quantity, average price, cost basis, dividends. */
interface AssetMetricsServiceInterface
{
    public function compute(Asset $asset): AssetMetricsDto;
}
