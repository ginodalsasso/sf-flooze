<?php

declare(strict_types=1);

namespace App\Dto\Finance;

/**
 * One crypto returned by a provider search: what it takes to fill an Asset.
 *
 * The provider's own identifier is deliberately absent — `asset` stores no such column,
 * so a ticker is re-resolved at pricing time (see CryptoPriceApiClient).
 */
final readonly class CryptoCoinDto
{
    public function __construct(
        public string $ticker,
        public string $name,
    ) {}
}
