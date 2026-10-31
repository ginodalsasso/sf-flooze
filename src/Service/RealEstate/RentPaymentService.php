<?php

declare(strict_types=1);

namespace App\Service\RealEstate;

use App\Dto\Finance\TransactionInputDto;
use App\Entity\Account;
use App\Entity\Category;
use App\Entity\Lease;
use App\Entity\RentPayment;
use App\Enum\RentPaymentStatusEnum;
use App\Enum\TransactionTypeEnum;
use App\Service\Finance\Contract\TransactionServiceInterface;
use App\Service\RealEstate\Contract\LeaseRentScheduleServiceInterface;
use App\Service\RealEstate\Contract\RentPaymentServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

final class RentPaymentService implements RentPaymentServiceInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TransactionServiceInterface $transactionService,
        private readonly LeaseRentScheduleServiceInterface $schedule,
        private readonly ClockInterface $clock,
    ) {}

    public function confirm(Lease $lease, \DateTimeImmutable $dueDate, Account $account, ?Category $category): RentPayment
    {
        $this->guardDueDate($lease, $dueDate);
        $this->guardInSpace($lease, $account, $category);

        return $this->em->wrapInTransaction(function () use ($lease, $dueDate, $account, $category) {
            $amount = bcadd($lease->getRent(), $lease->getCharges(), 2);

            $payment = (new RentPayment())
                ->setSpace($lease->getSpace())
                ->setLease($lease)
                ->setAmount($amount)
                ->setDueDate($dueDate)
                ->setPaidDate($dueDate)
                ->setStatus(RentPaymentStatusEnum::PAID);

            $input = new TransactionInputDto();
            $input->space = $lease->getSpace();
            $input->account = $account;
            $input->type = TransactionTypeEnum::INCOME;
            $input->amount = $amount;
            $input->date = $dueDate;
            $input->description = 'Loyer — ' . $lease->getProperty()->getName();
            $input->category = $category;

            $transaction = $this->transactionService->save($input);
            $payment->setTransaction($transaction);

            $this->em->persist($payment);
            $this->em->flush();

            return $payment;
        });
    }

    /** No Transaction: an unpaid rent never touches the ledger. */
    public function markLate(Lease $lease, \DateTimeImmutable $dueDate): RentPayment
    {
        $this->guardDueDate($lease, $dueDate);

        $payment = (new RentPayment())
            ->setSpace($lease->getSpace())
            ->setLease($lease)
            ->setAmount(bcadd($lease->getRent(), $lease->getCharges(), 2))
            ->setDueDate($dueDate)
            ->setPaidDate(null)
            ->setStatus(RentPaymentStatusEnum::LATE);

        $this->em->persist($payment);
        $this->em->flush();

        return $payment;
    }

    /**
     * Only the oldest due occurrence may be treated. The date comes from a POST, so it is
     * forgeable and has to be checked either way — the schedule is the source of truth.
     */
    private function guardDueDate(Lease $lease, \DateTimeImmutable $dueDate): void
    {
        $due = $this->schedule->nextDue($lease, $this->clock->now());

        if ($due === null || $due->dueDate != $dueDate->setTime(0, 0)) {
            throw new \InvalidArgumentException(
                'Cette échéance ne peut pas être traitée : il faut d\'abord régler la plus ancienne.',
            );
        }
    }

    /**
     * Rejects an account or category belonging to another space. The form only offers entities
     * of the space, but a posted id proves nothing.
     */
    private function guardInSpace(Lease $lease, Account $account, ?Category $category): void
    {
        $owners = [
            'compte' => $account->getSpace(),
            'catégorie' => $category?->getSpace(),
        ];

        foreach ($owners as $label => $owner) {
            if ($owner !== null && $owner->getId() !== $lease->getSpace()->getId()) {
                throw new \InvalidArgumentException(sprintf('Élément hors de cet espace : %s.', $label));
            }
        }
    }
}
