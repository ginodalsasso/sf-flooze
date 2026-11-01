<?php

declare(strict_types=1);

namespace App\Service\Tax\Contract;

use App\Dto\Tax\TaxSummaryDto;
use App\Entity\Space;

/**
 * Builds the declaration view of a fiscal year from the ledger.
 *
 * Read-only: nothing is persisted, so the page always reflects the transactions as they
 * stand and can never drift from them.
 */
interface TaxSummaryServiceInterface
{
    public function summarize(Space $space, ?int $year = null): TaxSummaryDto;
}
