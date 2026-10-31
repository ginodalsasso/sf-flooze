<?php

declare(strict_types=1);

namespace App\Service\RealEstate\Contract;

use App\Dto\RealEstate\LeaseTermDto;
use App\Entity\Lease;

/**
 * Resolves how long a lease actually runs.
 *
 * A lease left alone renews itself: the term is computed on read from the signed end date,
 * so no scheduled task and no write are involved.
 */
interface LeaseTermServiceInterface
{
    public function currentTerm(Lease $lease): LeaseTermDto;
}
