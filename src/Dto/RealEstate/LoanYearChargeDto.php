<?php

declare(strict_types=1);

namespace App\Dto\RealEstate;

use App\Entity\Loan;

/** What one loan costs in interest and insurance over a given year, read from its schedule. */
final readonly class LoanYearChargeDto
{
    public function __construct(
        public Loan $loan,
        public string $interestPart,
        public string $insurancePart,
        public int $instalmentCount,
    ) {}

    /** The only part of an instalment a rental income can deduct. */
    public function deductiblePart(): string
    {
        return bcadd($this->interestPart, $this->insurancePart, 2);
    }
}
