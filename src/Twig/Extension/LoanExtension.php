<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Entity\Loan;
use App\Service\RealEstate\Contract\LoanAmortizationServiceInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class LoanExtension extends AbstractExtension
{
    public function __construct(
        private readonly LoanAmortizationServiceInterface $amortizationService,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('loan_monthly_payment', [$this, 'getMonthlyPayment']),
            new TwigFunction('loan_borrowed_capital', [$this, 'getBorrowedCapital']),
        ];
    }

    public function getMonthlyPayment(Loan $loan): string
    {
        return $this->amortizationService->monthlyPayment($loan);
    }

    public function getBorrowedCapital(Loan $loan): string
    {
        return $this->amortizationService->borrowedCapital($loan);
    }
}
