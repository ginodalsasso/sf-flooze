<?php

declare(strict_types=1);

namespace App\Dto\Finance;

/**
 * Price of one unit of an asset, in the asset currency, with the date it refers to.
 *
 * The date is part of the price: a manually recorded price ages, a quote from a market
 * API does not. Callers display it so the user knows what the figure is worth.
 */
final readonly class AssetPriceDto
{
    public function __construct(
        public string $unitPrice,
        public \DateTimeImmutable $asOf,
    ) {}
}
