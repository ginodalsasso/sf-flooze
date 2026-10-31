<?php

declare(strict_types=1);

namespace App\Service\RealEstate;

use App\Dto\RealEstate\RentDueDto;
use App\Entity\Lease;
use App\Repository\Contract\RentPaymentRepositoryInterface;
use App\Service\Date\Contract\DateShifterInterface;
use App\Service\RealEstate\Contract\LeaseRentScheduleServiceInterface;
use App\Service\RealEstate\Contract\LeaseTermServiceInterface;

final class LeaseRentScheduleService implements LeaseRentScheduleServiceInterface
{
    public function __construct(
        private readonly DateShifterInterface $dateShifter,
        private readonly LeaseTermServiceInterface $leaseTermService,
        private readonly RentPaymentRepositoryInterface $rentPaymentRepository,
    ) {}

    /** Month 0 is the lease's own start date: the first rent is due at move-in. */
    public function nextDue(Lease $lease, \DateTimeImmutable $today): ?RentDueDto
    {
        if (!$lease->isActive()) {
            return null;
        }

        $today = $today->setTime(0, 0);
        $start = $lease->getStartDate()->setTime(0, 0);
        $end = min($today, $this->leaseTermService->currentTerm($lease)->endDate);

        if ($start > $end) {
            return null;
        }

        $amount = bcadd($lease->getRent(), $lease->getCharges(), 2);
        $dueDates = [];
        $month = 0;

        while (($candidate = $this->dateShifter->addMonths($start, $month)) <= $end) {
            if ($this->rentPaymentRepository->findByLeaseAndDueDate($lease, $candidate) === null) {
                $dueDates[] = $candidate;
            }
            ++$month;
        }

        if ($dueDates === []) {
            return null;
        }

        return new RentDueDto($lease, $dueDates[0], $amount, count($dueDates) - 1);
    }
}
