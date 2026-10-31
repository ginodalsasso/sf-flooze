<?php

declare(strict_types=1);

namespace App\Service\RealEstate;

use App\Dto\RealEstate\AmortizationRowDto;
use App\Dto\RealEstate\LoanChargesDto;
use App\Dto\RealEstate\LoanYearChargeDto;
use App\Entity\Loan;
use App\Entity\Space;
use App\Repository\Contract\LoanPaymentRepositoryInterface;
use App\Repository\Contract\LoanRepositoryInterface;
use App\Service\RealEstate\Contract\LoanAmortizationServiceInterface;
use App\Service\RealEstate\Contract\LoanChargeServiceInterface;

final class LoanChargeService implements LoanChargeServiceInterface
{
    public function __construct(
        private readonly LoanRepositoryInterface $loanRepository,
        private readonly LoanAmortizationServiceInterface $amortizationService,
        private readonly LoanPaymentRepositoryInterface $loanPaymentRepository,
    ) {}

    public function chargesForYear(Space $space, int $year): LoanChargesDto
    {
        $perLoan = [];
        $interestTotal = '0.00';
        $insuranceTotal = '0.00';

        foreach ($this->loanRepository->findBySpace($space) as $loan) {
            $rows = $this->rowsOfYear($this->amortizationService->schedule($loan)->combined->rows, $year);

            // A loan with no instalment that year has nothing to say about it.
            if ($rows === []) {
                continue;
            }

            $blend = $this->blendWithConfirmed($loan, $rows);

            $perLoan[] = new LoanYearChargeDto(
                loan: $loan,
                interestPart: $blend['interest'],
                insurancePart: $blend['insurance'],
                instalmentCount: $blend['count'],
            );

            $interestTotal = bcadd($interestTotal, $blend['interest'], 2);
            $insuranceTotal = bcadd($insuranceTotal, $blend['insurance'], 2);
        }

        return new LoanChargesDto($year, $perLoan, $interestTotal, $insuranceTotal);
    }

    /** @param AmortizationRowDto[] $rows */
    private function rowsOfYear(array $rows, int $year): array
    {
        return array_values(array_filter(
            $rows,
            static fn (AmortizationRowDto $row) => (int) $row->dueDate->format('Y') === $year,
        ));
    }

    /**
     * A confirmed LoanPayment freezes its four amounts at payment time — the theoretical row
     * for that month must not be used once a frozen one exists, or a later rate correction on
     * the LoanLine would silently change interest already declared for a past year.
     *
     * @param AmortizationRowDto[] $rows
     *
     * @return array{interest: string, insurance: string, count: int}
     */
    private function blendWithConfirmed(Loan $loan, array $rows): array
    {
        $interest = '0.00';
        $insurance = '0.00';

        foreach ($rows as $row) {
            $confirmed = $this->loanPaymentRepository->findByLoanAndMonth($loan, $row->monthNumber);
            $interest = bcadd($interest, $confirmed?->getInterestPart() ?? $row->interestPart, 2);
            $insurance = bcadd($insurance, $confirmed?->getInsurancePart() ?? $row->insurancePart, 2);
        }

        return ['interest' => $interest, 'insurance' => $insurance, 'count' => count($rows)];
    }
}
