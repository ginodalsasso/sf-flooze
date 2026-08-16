<?php

declare(strict_types=1);

namespace App\Controller\Finance;

use App\Enum\CurrencyEnum;
use App\Service\Finance\Contract\ExchangeRateServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/finance/exchange-rate', name: 'app_exchange_rate_')]
class ExchangeRateController extends AbstractController
{
    /** Amounts are read as raw text: bcmath rejects exponents and thousand separators. */
    private const AMOUNT_PATTERN = '/^\d{1,12}([.,]\d{1,6})?$/';

    public function __construct(
        private readonly ExchangeRateServiceInterface $exchangeRateService,
    ) {}

    /** Spot conversion for the dashboard converter. Rates are public data, nothing here is space-scoped. */
    #[Route('/convert', name: 'convert', methods: ['GET'])]
    public function convert(Request $request): JsonResponse
    {
        $amount = (string) $request->query->get('amount', '');
        $from = CurrencyEnum::tryFrom((string) $request->query->get('from', ''));
        $to = CurrencyEnum::tryFrom((string) $request->query->get('to', ''));

        if ($from === null || $to === null || preg_match(self::AMOUNT_PATTERN, $amount) !== 1) {
            return $this->json(['error' => 'Requête de conversion invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $amount = str_replace(',', '.', $amount);

        return $this->json([
            'from'   => $from->value,
            'to'     => $to->value,
            'rate'   => $this->exchangeRateService->getRate($from, $to),
            'result' => $this->exchangeRateService->convert($amount, $from, $to),
        ]);
    }
}
