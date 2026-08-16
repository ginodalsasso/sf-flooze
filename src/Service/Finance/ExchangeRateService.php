<?php

declare(strict_types=1);

namespace App\Service\Finance;

use App\Enum\CurrencyEnum;
use App\Service\Finance\Contract\ExchangeRateApiClientInterface;
use App\Service\Finance\Contract\ExchangeRateServiceInterface;

/**
 * Single source of exchange rates for the whole application.
 *
 * Rates come from the exchange rate provider, at the requested date. The table below is the
 * offline fallback: an unreachable provider must degrade the rate, never block an operation.
 */
final class ExchangeRateService implements ExchangeRateServiceInterface
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

    public function __construct(
        private readonly ExchangeRateApiClientInterface $exchangeRateApiClient,
    ) {}

    public function getRate(CurrencyEnum $from, CurrencyEnum $to, ?\DateTimeImmutable $date = null): string
    {
        if ($from === $to) {
            return '1.000000';
        }

        $publishedRate = $this->exchangeRateApiClient->fetchRate($from, $to, $date ?? new \DateTimeImmutable('today'));

        return $publishedRate ?? $this->fallbackRate($from, $to);
    }

    public function convert(string $amount, CurrencyEnum $from, CurrencyEnum $to, ?\DateTimeImmutable $date = null): string
    {
        if ($from === $to) {
            return bcadd($amount, '0', 2);
        }

        return bcmul($amount, $this->getRate($from, $to, $date), 2);
    }

    private function fallbackRate(CurrencyEnum $from, CurrencyEnum $to): string
    {
        return bcdiv(self::RATES_TO_EUR[$from->value], self::RATES_TO_EUR[$to->value], self::SCALE);
    }
}
