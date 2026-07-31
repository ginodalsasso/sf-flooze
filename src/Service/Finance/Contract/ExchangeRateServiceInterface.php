<?php

declare(strict_types=1);

namespace App\Service\Finance\Contract;

use App\Enum\CurrencyEnum;

/** Single source of exchange rates for the whole application. */
interface ExchangeRateServiceInterface
{
    /** Rate converting an amount from $from to $to. */
    public function getRate(CurrencyEnum $from, CurrencyEnum $to): string;

    /** Converts a monetary amount, rounded to 2 decimals for storage and display. */
    public function convert(string $amount, CurrencyEnum $from, CurrencyEnum $to): string;
}
