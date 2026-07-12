<?php

declare(strict_types=1);

namespace App\Dto\Finance;

use App\Entity\Asset;

/**
 * View model for one row in the asset list.
 *
 * It pairs an asset with its computed metrics so the template receives a single
 * object instead of two parallel arrays keyed by asset ID.
 */
final readonly class AssetListItemDto
{
    public function __construct(
        public Asset $asset,
        public AssetMetricsDto $metrics,
    ) {}

    public function hasPosition(): bool
    {
        return $this->metrics->hasPosition();
    }
}
