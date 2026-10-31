<?php

declare(strict_types=1);

namespace App\Dto\RealEstate;

/** One instalment of the schedule. Every amount is in the currency of the loan's space. */
final readonly class AmortizationRowDto
{
    public function __construct(
        public int $monthNumber,
        public \DateTimeImmutable $dueDate,
        public string $capitalPart,
        /** Deductible on a rental property. */
        public string $interestPart,
        /** Deductible on a rental property. */
        public string $insurancePart,
        /** What actually leaves the account: capital + interest + insurance. */
        public string $totalPayment,
        /** Capital still owed once this instalment is paid. */
        public string $remainingCapital,
    ) {}

    /** The only part of an instalment a rental income can deduct. */
    public function deductiblePart(): string
    {
        return bcadd($this->interestPart, $this->insurancePart, 2);
    }
}
