<?php

declare(strict_types=1);

namespace App\Dto\RealEstate;

/**
 * Loan charges of a space over one year, loan by loan.
 *
 * A blend of ledger and projection: a month already confirmed by a LoanPayment contributes its
 * frozen amounts, which do not move if the LoanLine's rate is corrected afterwards; a month not
 * yet confirmed still comes from the theoretical schedule, as a projection.
 */
final readonly class LoanChargesDto
{
    public function __construct(
        public int $year,
        /** @var LoanYearChargeDto[] */
        public array $perLoan,
        public string $interestTotal,
        public string $insuranceTotal,
    ) {}

    public function deductibleTotal(): string
    {
        return bcadd($this->interestTotal, $this->insuranceTotal, 2);
    }

    public function isEmpty(): bool
    {
        return $this->perLoan === [];
    }
}
