<?php

declare(strict_types=1);

namespace App\Service\RealEstate\Contract;

use App\Dto\RealEstate\AmortizationScheduleDto;
use App\Dto\RealEstate\LoanScheduleDto;
use App\Entity\Loan;
use App\Entity\LoanLine;

/**
 * Derives a loan's amortization schedule from the terms of its lines.
 *
 * A pure function of those terms: the same loan always yields the same schedule, so the
 * table is computed on read and never persisted.
 */
interface LoanAmortizationServiceInterface
{
    /** Each line on its own terms, plus the combined schedule the account actually sees. */
    public function schedule(Loan $loan): LoanScheduleDto;

    /** One line amortised alone; $startDate is the loan's release date, shared by every line. */
    public function lineSchedule(LoanLine $line, \DateTimeImmutable $startDate): AmortizationScheduleDto;

    /**
     * First combined instalment — capital, interest and insurance of every line.
     *
     * Built without the rows: a list of loans needs the instalment, not hundreds of lines.
     * Not constant over the loan when the lines have different durations.
     */
    public function monthlyPayment(Loan $loan): string;

    /** Capital borrowed across the lines of the file. */
    public function borrowedCapital(Loan $loan): string;
}
