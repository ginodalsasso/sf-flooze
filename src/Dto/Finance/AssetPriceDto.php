<?php

declare(strict_types=1);

namespace App\Dto\Finance;

use App\Enum\AssetPriceSourceEnum;

/**
 * Price of one unit of an asset, in the asset currency, with the date it refers to.
 *
 * The date and the source are part of the price: a live quote, a quote read before the
 * provider went unreachable and a manually recorded trade are not worth the same. Callers
 * display both so the user knows what the figure is worth.
 */
final readonly class AssetPriceDto
{
    public function __construct(
        public string $unitPrice,
        public \DateTimeImmutable $asOf,
        public AssetPriceSourceEnum $source,
    ) {}
}
