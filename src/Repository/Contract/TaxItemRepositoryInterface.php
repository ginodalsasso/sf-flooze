<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Entity\TaxItem;
use App\Entity\TaxYear;
use App\Enum\FiscalBucketEnum;
use App\Enum\FiscalRoleEnum;
use Doctrine\Persistence\ObjectRepository;

/**
 * @extends ObjectRepository<TaxItem>
 */
interface TaxItemRepositoryInterface extends ObjectRepository
{
    /** The checkbox for this bucket × role of this dossier, if it has ever been toggled. */
    public function findOne(TaxYear $taxYear, FiscalBucketEnum $bucket, FiscalRoleEnum $role): ?TaxItem;

    /** @return TaxItem[] every checkbox toggled at least once for this dossier */
    public function findByTaxYear(TaxYear $taxYear): array;
}
