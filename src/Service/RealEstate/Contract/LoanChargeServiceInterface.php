<?php

declare(strict_types=1);

namespace App\Service\RealEstate\Contract;

use App\Dto\RealEstate\LoanChargesDto;
use App\Entity\Space;

/**
 * Reads the interest and insurance the space's loans carry over a year.
 *
 * Exposed so a caller outside RealEstate — the declaration screen — can show the figure
 * without reaching into loans itself.
 */
interface LoanChargeServiceInterface
{
    public function chargesForYear(Space $space, int $year): LoanChargesDto;
}
