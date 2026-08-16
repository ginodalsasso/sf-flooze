<?php

declare(strict_types=1);

namespace App\Service\Finance\Contract;

use App\Enum\CurrencyEnum;

/** Reads published reference rates from the exchange rate provider. */
interface ExchangeRateApiClientInterface
{
    /**
     * Value of one unit of $from expressed in $to on $date, or null when the rate cannot be read.
     *
     * Null covers every unusable answer — network failure, unknown currency, date outside the
     * published range — so the caller has a single degraded case to handle.
     */
    public function fetchRate(CurrencyEnum $from, CurrencyEnum $to, \DateTimeImmutable $date): ?string;
}
