<?php

declare(strict_types=1);

namespace App\Service\RealEstate\Contract;

use App\Entity\Loan;

/** Loan persistence. A loan has no soft delete (ERD): it is kept or removed outright. */
interface LoanServiceInterface
{
    /** @throws \InvalidArgumentException when the loan is inconsistent or crosses a space boundary */
    public function save(Loan $loan): void;

    public function delete(Loan $loan): void;
}
