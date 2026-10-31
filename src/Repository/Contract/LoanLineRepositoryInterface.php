<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Entity\LoanLine;
use Doctrine\Persistence\ObjectRepository;

/**
 * Lines are always reached through their loan, which loads them eagerly enough; no query of
 * its own is needed yet.
 *
 * @extends ObjectRepository<LoanLine>
 */
interface LoanLineRepositoryInterface extends ObjectRepository
{
}
