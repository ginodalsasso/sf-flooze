<?php

declare(strict_types=1);

namespace App\Service\Finance;

use App\Enum\CurrencyEnum;
use App\Service\Finance\Contract\ExchangeRateApiClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reads published reference rates over HTTP.
 *
 * The provider publishes one rate per business day and never revises it, so a past rate is
 * cached long and a same-day rate short — the day's rate lands in the afternoon.
 */
final class ExchangeRateApiClient implements ExchangeRateApiClientInterface
{
    private const PAST_RATE_TTL = 2592000; // 30 days
    private const TODAY_RATE_TTL = 3600;
    private const FAILURE_TTL = 60;

    private const SCALE = 6;

    private const API_DATE_FORMAT = 'Y-m-d';

    public function __construct(
        private readonly HttpClientInterface $exchangeRateClient,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly ClockInterface $clock,
    ) {}

    public function fetchRate(CurrencyEnum $from, CurrencyEnum $to, \DateTimeImmutable $date): ?string
    {
        // Read once: two reads could straddle midnight and disagree on the cache key.
        $today = $this->clock->now()->modify('midnight');
        $day = $this->capToToday($date, $today);
        $key = sprintf('exchange_rate.%s.%s.%s', $from->value, $to->value, $day->format(self::API_DATE_FORMAT));

        return $this->cache->get($key, function (ItemInterface $item) use ($from, $to, $day, $today): ?string {
            $rate = $this->requestRate($from, $to, $day);
            $item->expiresAfter($this->cacheDuration($rate, $day, $today));

            return $rate;
        });
    }

    /** Nothing is published ahead of time: a future-dated operation uses the latest known rate. */
    private function capToToday(\DateTimeImmutable $date, \DateTimeImmutable $today): \DateTimeImmutable
    {
        return $date > $today ? $today : $date;
    }

    /** A failure must not squat the key, and today's rate is still moving until it is published. */
    private function cacheDuration(?string $rate, \DateTimeImmutable $day, \DateTimeImmutable $today): int
    {
        if ($rate === null) {
            return self::FAILURE_TTL;
        }

        return $day == $today ? self::TODAY_RATE_TTL : self::PAST_RATE_TTL;
    }

    private function requestRate(CurrencyEnum $from, CurrencyEnum $to, \DateTimeImmutable $day): ?string
    {
        try {
            $response = $this->exchangeRateClient->request('GET', sprintf('rate/%s/%s', $from->value, $to->value), [
                'query' => ['date' => $day->format(self::API_DATE_FORMAT)],
            ]);

            $rate = $response->toArray()['rate'] ?? null;
        } catch (ExceptionInterface $exception) {
            $this->logger->warning('Exchange rate lookup failed for {pair} on {date}: {reason}', [
                'pair' => $from->value . '/' . $to->value,
                'date' => $day->format(self::API_DATE_FORMAT),
                'reason' => $exception->getMessage(),
            ]);

            return null;
        }

        if (!is_numeric($rate)) {
            return null;
        }

        return bcadd((string) $rate, '0', self::SCALE);
    }
}
