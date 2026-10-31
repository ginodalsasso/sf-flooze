<?php

declare(strict_types=1);

namespace App\Dto\RealEstate;

use App\Entity\LoanLine;

/** A loan line paired with its own schedule, so each rate can be read on its own terms. */
final readonly class LoanLineScheduleDto
{
    public function __construct(
        public LoanLine $line,
        public AmortizationScheduleDto $schedule,
    ) {}
}
