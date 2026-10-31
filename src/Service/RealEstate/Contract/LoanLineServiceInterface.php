<?php

declare(strict_types=1);

namespace App\Service\RealEstate\Contract;

use App\Entity\LoanLine;

/** Persistence of the lines a loan is made of. */
interface LoanLineServiceInterface
{
    /** @throws \InvalidArgumentException when the terms cannot produce a sound schedule */
    public function save(LoanLine $line): void;

    public function delete(LoanLine $line): void;
}
