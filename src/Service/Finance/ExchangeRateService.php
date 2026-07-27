<?php

declare(strict_types=1);

namespace App\Service\Finance;

use App\Enum\CurrencyEnum;

/**
 * Single source of exchange rates for the whole application.
 *
 * Rates are held here rather than on CurrencyEnum so that plugging a live rate
 * API later changes this class only: no caller and no schema is impacted. The
 * table below then becomes the offline fallback.
 */
final class ExchangeRateService
{
    /** Value of one unit of the currency, expressed in EUR (pivot). Updated manually. */
    private const RATES_TO_EUR = [
        'EUR' => '1.000000',
        'USD' => '0.920000',
        'GBP' => '1.170000',
        'CHF' => '1.060000',
        'NZD' => '0.550000',
    ];

    private const SCALE = 6;

    /** Rate converting an amount from $from to $to. */
    public function getRate(CurrencyEnum $from, CurrencyEnum $to): string
    {
        if ($from === $to) {
            return '1.000000';
        }

        return bcdiv(self::RATES_TO_EUR[$from->value], self::RATES_TO_EUR[$to->value], self::SCALE);
    }

    /** Converts a monetary amount, rounded to 2 decimals for storage and display. */
    public function convert(string $amount, CurrencyEnum $from, CurrencyEnum $to): string
    {
        if ($from === $to) {
            return bcadd($amount, '0', 2);
        }

        return bcmul($amount, $this->getRate($from, $to), 2);
    }
}
