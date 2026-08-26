<?php

declare(strict_types=1);

namespace App\Service\Finance;

use App\Dto\Finance\AssetPriceDto;
use App\Dto\Finance\CryptoCoinDto;
use App\Enum\AssetPriceSourceEnum;
use App\Enum\CurrencyEnum;
use App\Service\Finance\Contract\CryptoPriceApiClientInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reads crypto spot prices and searches the coin catalogue over HTTP (CoinGecko public API).
 *
 * The API is free and public, but it has a rate limit. The cache is used to avoid hitting the limit.
 * The cache is also used to avoid hitting the API when the service is offline.
 */
final class CryptoPriceApiClient implements CryptoPriceApiClientInterface
{
    private const QUOTE_TTL = 300;
    private const SEARCH_TTL = 604800; // 7 days
    private const FAILURE_TTL = 60;

    private const SEARCH_LIMIT = 10;

    /** Same scale as asset_entry.unit_price: a price the ledger cannot store is not worth reading. */
    private const SCALE = 4;

    public function __construct(
        private readonly HttpClientInterface $cryptoPriceClient,
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly ClockInterface $clock,
        private readonly string $cryptoPriceApiKey,
    ) {}

    public function fetchPrice(string $ticker, CurrencyEnum $currency): ?AssetPriceDto
    {
        $quote = $this->cachedQuote(strtoupper($ticker), $currency);

        if ($quote === null) {
            return null;
        }

        $now = $this->clock->now();

        return new AssetPriceDto(
            $quote['price'],
            $now->setTimestamp($quote['at']),
            $now->getTimestamp() - $quote['at'] <= self::QUOTE_TTL
                ? AssetPriceSourceEnum::MARKET
                : AssetPriceSourceEnum::CACHED,
        );
    }

    public function searchCoins(string $query): array
    {
        $coins = [];

        foreach (array_slice($this->coinSearch($query), 0, self::SEARCH_LIMIT) as $coin) {
            $ticker = strtoupper((string) ($coin['symbol'] ?? ''));
            $name = (string) ($coin['name'] ?? '');

            if ($ticker !== '' && $name !== '') {
                $coins[] = new CryptoCoinDto($ticker, $name);
            }
        }

        return $coins;
    }

    /**
     * @return array{price: string, at: int}|null
     */
    private function cachedQuote(string $ticker, CurrencyEnum $currency): ?array
    {
        $key = sprintf('crypto_price.%s.%s', rawurlencode($ticker), $currency->value);
        $gate = $this->cache->getItem($key . '.gate');
        $quote = $this->cache->getItem($key);

        if ($gate->isHit()) {
            return $quote->get();
        }

        $price = $this->requestPrice($ticker, $currency);
        $this->cache->save($gate->set(true)->expiresAfter($price === null ? self::FAILURE_TTL : self::QUOTE_TTL));

        if ($price !== null) {
            $at = $this->clock->now()->getTimestamp();
            $this->cache->save($quote->set(['price' => $price, 'at' => $at])->expiresAfter(null));
        }

        return $quote->get();
    }

    private function requestPrice(string $ticker, CurrencyEnum $currency): ?string
    {
        $coinId = $this->resolveCoinId($ticker);

        if ($coinId === null) {
            return null;
        }

        $vsCurrency = strtolower($currency->value);

        try {
            $response = $this->cryptoPriceClient->request('GET', 'simple/price', [
                'query' => ['ids' => $coinId, 'vs_currencies' => $vsCurrency],
            ] + $this->authOptions());

            $price = $response->toArray()[$coinId][$vsCurrency] ?? null; // ex: {"bitcoin":{"usd":27345.0}}
        } catch (ExceptionInterface $exception) {
            $this->logger->warning('Crypto price lookup failed for {pair}: {reason}', [
                'pair' => $ticker . '/' . $currency->value,
                'reason' => $exception->getMessage(),
            ]);

            return null;
        }

        if (!is_numeric($price) || (float) $price <= 0) {
            return null;
        }

        // sprintf, not a cast: a micro-cap price comes back as a float in exponent notation.
        return sprintf('%.' . self::SCALE . 'F', $price);
    }

    /**
     * A ticker is not an API id ("BTC" is "bitcoin"). Search results are ranked by market cap,
     * so the first exact symbol match is the coin the ticker means — the dominant one, which is
     * the only reading available since `asset` stores no provider id.
     */
    private function resolveCoinId(string $ticker): ?string
    {
        foreach ($this->coinSearch($ticker) as $coin) {
            if (strtoupper((string) ($coin['symbol'] ?? '')) === $ticker) {
                return $coin['id'] ?? null;
            }
        }

        return null;
    }

    /**
     * Ticker/name searches barely move, so results are cached for a week. A failure is cached
     * briefly too: offline, a search box must fail fast instead of timing out on each keystroke.
     *
     * @return array<int, array<string, mixed>>
     */
    private function coinSearch(string $query): array
    {
        $item = $this->cache->getItem('crypto_search.' . rawurlencode(mb_strtolower($query)));

        if ($item->isHit()) {
            return $item->get();
        }

        try {
            $coins = $this->cryptoPriceClient->request('GET', 'search', [
                'query' => ['query' => $query],
            ] + $this->authOptions())->toArray()['coins'] ?? [];
        } catch (ExceptionInterface $exception) {
            $this->logger->warning('Crypto search failed for {query}: {reason}', [
                'query' => $query,
                'reason' => $exception->getMessage(),
            ]);

            $coins = null;
        }

        $this->cache->save($item->set($coins ?? [])->expiresAfter($coins === null ? self::FAILURE_TTL : self::SEARCH_TTL));

        return $coins ?? [];
    }

    /** The public API works without a key, at a lower rate limit. */
    private function authOptions(): array
    {
        if ($this->cryptoPriceApiKey === '') {
            return [];
        }

        return ['headers' => ['x-cg-demo-api-key' => $this->cryptoPriceApiKey]];
    }
}
