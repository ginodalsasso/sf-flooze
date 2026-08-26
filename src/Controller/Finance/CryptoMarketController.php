<?php

declare(strict_types=1);

namespace App\Controller\Finance;

use App\Dto\Finance\CryptoCoinDto;
use App\Enum\CurrencyEnum;
use App\Service\Date\Contract\DateFormatterInterface;
use App\Service\Finance\Contract\CryptoPriceApiClientInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Feeds the crypto picker of the asset form.
 *
 * Market data is public and nothing is written here, so these endpoints are not space-scoped —
 * same reasoning as ExchangeRateController. The prices they return are informative: the ones
 * that reach the ledger are re-read server-side when the form is submitted.
 */
#[Route('/finance/crypto', name: 'app_crypto_')]
class CryptoMarketController extends AbstractController
{
    private const QUERY_MIN = 2;
    private const QUERY_MAX = 40;

    /** Same shape as Asset::ticker, which is what the picker fills. */
    private const TICKER_PATTERN = '/^[A-Za-z0-9.\-]{1,20}$/';

    public function __construct(
        private readonly CryptoPriceApiClientInterface $cryptoPriceApiClient,
        private readonly DateFormatterInterface $dateFormatter,
    ) {}

    #[Route('/search', name: 'search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $query = trim($request->query->getString('q'));
        $length = mb_strlen($query);

        if ($length < self::QUERY_MIN || $length > self::QUERY_MAX) {
            return $this->json(['coins' => []]);
        }

        $getSearchResultArray = fn(CryptoCoinDto $coin) => 
            [
                'ticker' => $coin->ticker, 
                'name' => $coin->name
            ];

        return $this->json(['coins' => array_map(
            $getSearchResultArray,
            $this->cryptoPriceApiClient->searchCoins($query),
        )]);
    }

    #[Route('/price', name: 'price', methods: ['GET'])]
    public function price(Request $request): JsonResponse
    {
        $ticker = trim($request->query->getString('ticker'));
        $currency = CurrencyEnum::tryFrom($request->query->getString('currency'));

        if ($currency === null || preg_match(self::TICKER_PATTERN, $ticker) !== 1) {
            return $this->json(['error' => 'Requête de cotation invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $price = $this->cryptoPriceApiClient->fetchPrice($ticker, $currency);

        return $this->json([
            'price' => $price === null ? null : [
            'unitPrice' => $price->unitPrice,
            'currency'  => $currency->value,
            'source'    => $price->source->value,
            'label'     => $price->source->label(),
            // Rendered server-side: the picker must read the date exactly like the rest of the UI.
            'asOfLabel' => $this->dateFormatter->dateTime($price->asOf),
        ]]);
    }
}
