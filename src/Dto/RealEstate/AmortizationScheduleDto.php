<?php

declare(strict_types=1);

namespace App\Dto\RealEstate;

/**
 * The full amortization schedule of a loan, derived from its terms.
 *
 * Never stored: capital, rate, insurance rate, start date and duration determine it entirely,
 * so persisting it would only create a second version of the truth that could drift.
 */
final readonly class AmortizationScheduleDto
{
    public function __construct(
        /** Instalment actually debited each month: capital + interest + insurance. */
        public string $monthlyPayment,
        public string $totalInterest,
        public string $totalInsurance,
        public string $totalPaid,
        /** @var AmortizationRowDto[] */
        public array $rows,
        /** @var AmortizationYearDto[] oldest year first */
        public array $years,
    ) {}

    /** Everything the loan costs on top of the capital borrowed. */
    public function totalCost(): string
    {
        return bcadd($this->totalInterest, $this->totalInsurance, 2);
    }

    public function isEmpty(): bool
    {
        return $this->rows === [];
    }
}
