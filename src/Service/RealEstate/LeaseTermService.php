<?php

declare(strict_types=1);

namespace App\Service\RealEstate;

use App\Dto\RealEstate\LeaseTermDto;
use App\Entity\Lease;
use App\Service\Date\Contract\DateShifterInterface;
use App\Service\RealEstate\Contract\LeaseTermServiceInterface;
use Symfony\Component\Clock\ClockInterface;

final class LeaseTermService implements LeaseTermServiceInterface
{
    /** Length of a tacit renewal. */
    private const int RENEWAL_YEARS = 1;

    public function __construct(
        private readonly DateShifterInterface $dateShifter,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * Rolls the signed term forward by one year for each renewal already elapsed.
     *
     * Closing the lease is the only way to stop it: an inactive lease keeps its signed end date.
     */
    public function currentTerm(Lease $lease): LeaseTermDto
    {
        $endDate = $lease->getEndDate()->setTime(0, 0);

        if (!$lease->isActive()) {
            return new LeaseTermDto($endDate, 0);
        }

        $today = $this->clock->now()->setTime(0, 0);
        $renewalCount = 0;

        while ($endDate < $today) {
            $endDate = $this->dateShifter->addYears($endDate, self::RENEWAL_YEARS);
            ++$renewalCount;
        }

        return new LeaseTermDto($endDate, $renewalCount);
    }
}
