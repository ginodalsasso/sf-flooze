<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Entity\Loan;
use App\Entity\LoanPayment;
use App\Entity\Space;
use Doctrine\Persistence\ObjectRepository;

/**
 * @extends ObjectRepository<LoanPayment>
 */
interface LoanPaymentRepositoryInterface extends ObjectRepository
{
    /** @return LoanPayment[] loan payments of the space, most recent due date first */
    public function findBySpace(Space $space): array;

    /** @return LoanPayment[] loan payments of the loan, in instalment order */
    public function findByLoan(Loan $loan): array;

    /** The highest instalment number already recorded for the loan, if any. */
    public function findMaxMonthNumber(Loan $loan): ?int;

    /** The loan payment recorded for this instalment number, if one already exists. */
    public function findByLoanAndMonth(Loan $loan, int $monthNumber): ?LoanPayment;
}
