<?php

declare(strict_types=1);

namespace App\Service\RealEstate\Contract;

use App\Dto\RealEstate\AmortizationRowDto;
use App\Entity\Account;
use App\Entity\Category;
use App\Entity\Loan;
use App\Entity\LoanPayment;

/**
 * Confirms a loan's next due instalment.
 *
 * A LoanPayment only ever exists once paid: confirming also creates the linked
 * expense Transaction. The schedule itself stays computed, never stored.
 */
interface LoanPaymentServiceInterface
{
    /** Next unrecorded instalment of the loan, or null once it is fully amortised. Does not check whether it is due yet. */
    public function nextDueRow(Loan $loan): ?AmortizationRowDto;

    /** @throws \InvalidArgumentException if the loan is fully amortised, the next instalment is not due yet, or account/category belong to another space */
    public function confirm(Loan $loan, Account $account, ?Category $category): LoanPayment;
}
