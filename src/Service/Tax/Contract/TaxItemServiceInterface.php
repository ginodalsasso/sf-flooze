<?php

declare(strict_types=1);

namespace App\Service\Tax\Contract;

use App\Entity\TaxItem;
use App\Entity\TaxYear;
use App\Enum\FiscalBucketEnum;
use App\Enum\FiscalRoleEnum;

interface TaxItemServiceInterface
{
    /** Creates the checkbox done on first toggle, flips it otherwise. Never deletes it. */
    public function toggle(TaxYear $taxYear, FiscalBucketEnum $bucket, FiscalRoleEnum $role): TaxItem;
}
