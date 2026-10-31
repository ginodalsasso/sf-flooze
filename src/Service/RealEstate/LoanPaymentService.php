<?php

declare(strict_types=1);

namespace App\Service\RealEstate;

use App\Dto\Finance\TransactionInputDto;
use App\Dto\RealEstate\AmortizationRowDto;
use App\Entity\Account;
use App\Entity\Category;
use App\Entity\Loan;
use App\Entity\LoanPayment;
use App\Enum\TransactionTypeEnum;
use App\Repository\Contract\LoanPaymentRepositoryInterface;
use App\Service\Finance\Contract\TransactionServiceInterface;
use App\Service\RealEstate\Contract\LoanAmortizationServiceInterface;
use App\Service\RealEstate\Contract\LoanPaymentServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

final class LoanPaymentService implements LoanPaymentServiceInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TransactionServiceInterface $transactionService,
        private readonly LoanAmortizationServiceInterface $amortizationService,
        private readonly LoanPaymentRepositoryInterface $loanPaymentRepository,
        private readonly ClockInterface $clock,
    ) {}

    public function nextDueRow(Loan $loan): ?AmortizationRowDto
    {
        $nextMonth = ($this->loanPaymentRepository->findMaxMonthNumber($loan) ?? 0) + 1;

        foreach ($this->amortizationService->schedule($loan)->combined->rows as $row) {
            if ($row->monthNumber === $nextMonth) {
                return $row;
            }
        }

        return null;
    }

    public function confirm(Loan $loan, Account $account, ?Category $category): LoanPayment
    {
        $row = $this->nextDueRow($loan);

        if ($row === null) {
            throw new \InvalidArgumentException('Ce prêt est déjà intégralement remboursé.');
        }

        $this->guardDue($row);
        $this->guardInSpace($loan, $account, $category);

        return $this->em->wrapInTransaction(function () use ($loan, $row, $account, $category) {
            $payment = (new LoanPayment())
                ->setSpace($loan->getSpace())
                ->setLoan($loan)
                ->setMonthNumber($row->monthNumber)
                ->setDueDate($row->dueDate)
                ->setCapitalPart($row->capitalPart)
                ->setInterestPart($row->interestPart)
                ->setInsurancePart($row->insurancePart)
                ->setRemainingCapital($row->remainingCapital)
                ->setPaidDate($row->dueDate);

            $input = new TransactionInputDto();
            $input->space = $loan->getSpace();
            $input->account = $account;
            $input->type = TransactionTypeEnum::EXPENSE;
            $input->amount = $row->totalPayment;
            $input->date = $row->dueDate;
            $input->description = 'Mensualité emprunt — ' . $loan->getBankName();
            $input->category = $category;

            $transaction = $this->transactionService->save($input);
            $payment->setTransaction($transaction);

            $this->em->persist($payment);
            $this->em->flush();

            return $payment;
        });
    }

    /** The row's date comes from the computed schedule, not a POST, but confirm() can still be called early. */
    private function guardDue(AmortizationRowDto $row): void
    {
        if ($row->dueDate > $this->clock->now()->setTime(0, 0)) {
            throw new \InvalidArgumentException('Cette mensualité n\'est pas encore due.');
        }
    }

    /**
     * Rejects an account or category belonging to another space. The form only offers entities
     * of the space, but a posted id proves nothing.
     */
    private function guardInSpace(Loan $loan, Account $account, ?Category $category): void
    {
        $owners = [
            'compte' => $account->getSpace(),
            'catégorie' => $category?->getSpace(),
        ];

        foreach ($owners as $label => $owner) {
            if ($owner !== null && $owner->getId() !== $loan->getSpace()->getId()) {
                throw new \InvalidArgumentException(sprintf('Élément hors de cet espace : %s.', $label));
            }
        }
    }
}
