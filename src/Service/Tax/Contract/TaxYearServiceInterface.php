<?php

declare(strict_types=1);

namespace App\Service\Tax\Contract;

use App\Entity\Space;
use App\Entity\TaxYear;
use App\Enum\FiscalBucketEnum;
use App\Enum\TaxYearStatusEnum;

interface TaxYearServiceInterface
{
    /** The space's review dossier for this year, created in draft on first visit. */
    public function getOrCreate(Space $space, int $year): TaxYear;

    /** Only one step at a time, in either direction — a skipped step has no business meaning. */
    public function transition(TaxYear $taxYear, TaxYearStatusEnum $to): void;

    /** `$regime` is `'reel'`, `'micro'`, or `null` to clear the bucket's choice. */
    public function setRegimeChoice(TaxYear $taxYear, FiscalBucketEnum $bucket, ?string $regime): void;

    public function setNote(TaxYear $taxYear, ?string $note): void;
}
