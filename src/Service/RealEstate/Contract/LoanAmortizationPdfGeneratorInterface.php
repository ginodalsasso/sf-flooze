<?php

declare(strict_types=1);

namespace App\Service\RealEstate\Contract;

use App\Dto\RealEstate\LoanScheduleDto;
use App\Entity\Loan;

/** Renders an already computed loan schedule as PDF bytes. Reads nothing: the caller passes the schedule. */
interface LoanAmortizationPdfGeneratorInterface
{
    public function generate(Loan $loan, LoanScheduleDto $schedule): string;
}
