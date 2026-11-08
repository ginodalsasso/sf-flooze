<?php

declare(strict_types=1);

namespace App\Service\Tax\Contract;

use App\Dto\RealEstate\LoanChargesDto;
use App\Dto\Tax\TaxSummaryDto;

/** Formats an already computed declaration as CSV bytes. Reads nothing: the caller passes both figures. */
interface TaxDeclarationCsvGeneratorInterface
{
    public function generate(TaxSummaryDto $summary, LoanChargesDto $loanCharges): string;
}
