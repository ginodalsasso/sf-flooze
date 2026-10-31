<?php

declare(strict_types=1);

namespace App\Service\RealEstate;

use App\Dto\RealEstate\AmortizationRowDto;
use App\Dto\RealEstate\AmortizationScheduleDto;
use App\Dto\RealEstate\AmortizationYearDto;
use App\Dto\RealEstate\LoanLineScheduleDto;
use App\Dto\RealEstate\LoanScheduleDto;
use App\Entity\Loan;
use App\Entity\LoanLine;
use App\Service\Date\Contract\DateShifterInterface;
use App\Service\RealEstate\Contract\LoanAmortizationServiceInterface;

final class LoanAmortizationService implements LoanAmortizationServiceInterface
{
    /** Working precision of the rate arithmetic; every amount is rounded to 2 before it is kept. */
    private const int SCALE = 12;

    public function __construct(private readonly DateShifterInterface $dateShifter) {}

    public function schedule(Loan $loan): LoanScheduleDto
    {
        $lines = [];
        $borrowedCapital = '0.00';

        foreach ($loan->getLines() as $line) {
            $lines[] = new LoanLineScheduleDto($line, $this->lineSchedule($line, $loan->getStartDate()));
            $borrowedCapital = bcadd($borrowedCapital, $line->getAmount(), 2);
        }

        return new LoanScheduleDto(
            combined: $this->combine($lines),
            lines: $lines,
            borrowedCapital: $borrowedCapital,
        );
    }

    public function lineSchedule(LoanLine $line, \DateTimeImmutable $startDate): AmortizationScheduleDto
    {
        $months = $line->getDurationMonths();
        $capital = bcadd($line->getAmount(), '0', 2);

        if ($months < 1 || bccomp($capital, '0.00', 2) <= 0) {
            return $this->emptySchedule();
        }

        $monthlyRate = $this->monthlyRate($line->getRate());
        $monthlyInsurance = $this->monthlyInsurance($capital, $line->getInsuranceRate());
        // A bank-negotiated instalment (lissage across the file's other lines) replaces the
        // formula: interest still comes from the remaining capital, only the payment itself
        // is fixed instead of derived from capital/rate/duration.
        $payment = $line->getNegotiatedMonthlyPayment() ?? $this->annuity($capital, $monthlyRate, $months);

        $rows = $this->buildRows($startDate, $capital, $monthlyRate, $monthlyInsurance, $payment, $months);

        return $this->summarize($rows, bcadd($payment, $monthlyInsurance, 2));
    }

    public function monthlyPayment(Loan $loan): string
    {
        $total = '0.00';

        foreach ($loan->getLines() as $line) {
            $months = $line->getDurationMonths();
            $capital = bcadd($line->getAmount(), '0', 2);

            if ($months < 1 || bccomp($capital, '0.00', 2) <= 0) {
                continue;
            }

            $payment = $line->getNegotiatedMonthlyPayment() ?? $this->annuity($capital, $this->monthlyRate($line->getRate()), $months);

            $total = bcadd($total, bcadd(
                $payment,
                $this->monthlyInsurance($capital, $line->getInsuranceRate()),
                2,
            ), 2);
        }

        return $total;
    }

    public function borrowedCapital(Loan $loan): string
    {
        $total = '0.00';

        foreach ($loan->getLines() as $line) {
            $total = bcadd($total, $line->getAmount(), 2);
        }

        return $total;
    }

    /**
     * Merges the lines month by month into the single schedule the account actually sees.
     *
     * A line that has ended contributes nothing to the months after it: the combined
     * instalment therefore drops each time one of them finishes.
     *
     * @param LoanLineScheduleDto[] $lines
     */
    private function combine(array $lines): AmortizationScheduleDto
    {
        $perMonth = [];

        foreach ($lines as $lineSchedule) {
            foreach ($lineSchedule->schedule->rows as $row) {
                $perMonth[$row->monthNumber][] = $row;
            }
        }

        if ($perMonth === []) {
            return $this->emptySchedule();
        }

        ksort($perMonth);
        $rows = [];

        foreach ($perMonth as $monthNumber => $monthRows) {
            $rows[] = new AmortizationRowDto(
                monthNumber: $monthNumber,
                dueDate: $monthRows[0]->dueDate,
                capitalPart: $this->sum($monthRows, static fn(AmortizationRowDto $r) => $r->capitalPart),
                interestPart: $this->sum($monthRows, static fn(AmortizationRowDto $r) => $r->interestPart),
                insurancePart: $this->sum($monthRows, static fn(AmortizationRowDto $r) => $r->insurancePart),
                totalPayment: $this->sum($monthRows, static fn(AmortizationRowDto $r) => $r->totalPayment),
                remainingCapital: $this->sum($monthRows, static fn(AmortizationRowDto $r) => $r->remainingCapital),
            );
        }

        return $this->summarize($rows, $rows[0]->totalPayment);
    }

    /**
     * @param AmortizationRowDto[] $rows
     */
    private function summarize(array $rows, string $monthlyPayment): AmortizationScheduleDto
    {
        return new AmortizationScheduleDto(
            monthlyPayment: $monthlyPayment,
            totalInterest: $this->sum($rows, static fn(AmortizationRowDto $row) => $row->interestPart),
            totalInsurance: $this->sum($rows, static fn(AmortizationRowDto $row) => $row->insurancePart),
            totalPaid: $this->sum($rows, static fn(AmortizationRowDto $row) => $row->totalPayment),
            rows: $rows,
            years: $this->groupByYear($rows),
        );
    }

    private function emptySchedule(): AmortizationScheduleDto
    {
        return new AmortizationScheduleDto('0.00', '0.00', '0.00', '0.00', [], []);
    }

    /**
     * @return AmortizationRowDto[]
     */
    private function buildRows(
        \DateTimeImmutable $startDate,
        string $capital,
        string $monthlyRate,
        string $monthlyInsurance,
        string $monthlyPayment,
        int $months,
    ): array {
        $remaining = $capital;
        $rows = [];

        for ($month = 1; $month <= $months; ++$month) {
            $interestPart = $this->round2(bcmul($remaining, $monthlyRate, self::SCALE));
            $capitalPart = bcsub($monthlyPayment, $interestPart, 2);

            // The last instalment clears the line: the cent of rounding residue accumulated
            // along the way lands on the payment, never on the outstanding capital.
            if ($month === $months || bccomp($capitalPart, $remaining, 2) > 0) {
                $capitalPart = $remaining;
            }

            $remaining = bcsub($remaining, $capitalPart, 2);

            $rows[] = new AmortizationRowDto(
                monthNumber: $month,
                // First instalment falls one month after the funds are released.
                dueDate: $this->dateShifter->addMonths($startDate, $month),
                capitalPart: $capitalPart,
                interestPart: $interestPart,
                insurancePart: $monthlyInsurance,
                totalPayment: bcadd(bcadd($capitalPart, $interestPart, 2), $monthlyInsurance, 2),
                remainingCapital: $remaining,
            );

            if (bccomp($remaining, '0.00', 2) <= 0) {
                break;
            }
        }

        return $rows;
    }

    /** Annual percentage to a monthly fraction: 1.10 % becomes 0.000916666666. */
    private function monthlyRate(string $annualRatePercent): string
    {
        return bcdiv(bcdiv($annualRatePercent, '100', self::SCALE), '12', self::SCALE);
    }

    /** Insurance is charged on the capital borrowed, so the premium stays flat over the line. */
    private function monthlyInsurance(string $capital, string $annualRatePercent): string
    {
        return $this->round2(
            bcdiv(bcdiv(bcmul($capital, $annualRatePercent, self::SCALE), '100', self::SCALE), '12', self::SCALE),
        );
    }

    /**
     * Constant instalment covering capital and interest: C × i × (1+i)^n / ((1+i)^n − 1).
     */
    private function annuity(string $capital, string $monthlyRate, int $months): string
    {
        // A zero-rate line (PTZ) has no interest: the capital is simply split across instalments.
        if (bccomp($monthlyRate, '0', self::SCALE) === 0) {
            return $this->round2(bcdiv($capital, (string) $months, self::SCALE));
        }

        $growth = bcpow(bcadd('1', $monthlyRate, self::SCALE), (string) $months, self::SCALE);

        return $this->round2(bcdiv(
            bcmul(bcmul($capital, $monthlyRate, self::SCALE), $growth, self::SCALE),
            bcsub($growth, '1', self::SCALE),
            self::SCALE,
        ));
    }

    /**
     * @param AmortizationRowDto[] $rows
     *
     * @return AmortizationYearDto[]
     */
    private function groupByYear(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $year = (int) $row->dueDate->format('Y');

            $grouped[$year] ??= [
                'capital' => '0.00', 'interest' => '0.00', 'insurance' => '0.00', 'total' => '0.00', 'nb' => 0,
            ];

            $grouped[$year]['capital'] = bcadd($grouped[$year]['capital'], $row->capitalPart, 2);
            $grouped[$year]['interest'] = bcadd($grouped[$year]['interest'], $row->interestPart, 2);
            $grouped[$year]['insurance'] = bcadd($grouped[$year]['insurance'], $row->insurancePart, 2);
            $grouped[$year]['total'] = bcadd($grouped[$year]['total'], $row->totalPayment, 2);
            ++$grouped[$year]['nb'];
            // The last instalment of the year carries the capital still owed at 31 December.
            $grouped[$year]['remaining'] = $row->remainingCapital;
        }

        ksort($grouped);

        return array_map(
            static fn(int $year, array $totals) => new AmortizationYearDto(
                year: $year,
                capitalPart: $totals['capital'],
                interestPart: $totals['interest'],
                insurancePart: $totals['insurance'],
                totalPayment: $totals['total'],
                remainingCapital: $totals['remaining'],
                instalmentCount: $totals['nb'],
            ),
            array_keys($grouped),
            $grouped,
        );
    }

    /**
     * @param AmortizationRowDto[]                 $rows
     * @param callable(AmortizationRowDto): string $part
     */
    private function sum(array $rows, callable $part): string
    {
        $total = '0.00';

        foreach ($rows as $row) {
            $total = bcadd($total, $part($row), 2);
        }

        return $total;
    }

    /** bcmath truncates; a schedule needs half-up rounding or it drifts by cents every month. */
    private function round2(string $value): string
    {
        return bcadd($value, bccomp($value, '0', self::SCALE) < 0 ? '-0.005' : '0.005', 2);
    }
}
