<?php

declare(strict_types=1);

namespace App\Dto\RealEstate;

/**
 * The schedule of a whole loan file: the combined view, plus each line on its own.
 *
 * $combined merges the lines month by month. Its instalment is not constant when the lines
 * have different durations: it drops each time one of them ends.
 */
final readonly class LoanScheduleDto
{
    public function __construct(
        public AmortizationScheduleDto $combined,
        /** @var LoanLineScheduleDto[] largest capital first */
        public array $lines,
        /** Sum of the capital borrowed across the lines. */
        public string $borrowedCapital,
    ) {}

    public function isEmpty(): bool
    {
        return $this->combined->isEmpty();
    }

    /** True when the lines do not all end together, so the instalment changes over time. */
    public function hasStaggeredDurations(): bool
    {
        $durations = array_map(
            static fn(LoanLineScheduleDto $line) => $line->line->getDurationMonths(),
            $this->lines,
        );

        return count(array_unique($durations)) > 1;
    }
}
