<?php

declare(strict_types=1);

namespace App\Dto\RealEstate;

/**
 * One calendar year of the schedule.
 *
 * This is the shape the tax declaration needs: interest and insurance paid over a year are
 * deductible from rental income, the capital repaid is not.
 */
final readonly class AmortizationYearDto
{
    public function __construct(
        public int $year,
        public string $capitalPart,
        public string $interestPart,
        public string $insurancePart,
        public string $totalPayment,
        /** Capital still owed at the end of the year. */
        public string $remainingCapital,
        public int $instalmentCount,
    ) {}

    public function deductiblePart(): string
    {
        return bcadd($this->interestPart, $this->insurancePart, 2);
    }
}
