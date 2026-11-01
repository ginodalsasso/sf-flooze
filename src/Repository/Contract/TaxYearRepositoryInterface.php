<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Entity\Space;
use App\Entity\TaxYear;
use Doctrine\Persistence\ObjectRepository;

/**
 * @extends ObjectRepository<TaxYear>
 */
interface TaxYearRepositoryInterface extends ObjectRepository
{
    /** The space's review dossier for this year, if one already exists. */
    public function findBySpaceAndYear(Space $space, int $year): ?TaxYear;

    /** @return TaxYear[] every dossier of the space, most recent year first */
    public function findBySpace(Space $space): array;
}
