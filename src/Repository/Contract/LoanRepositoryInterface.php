<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Entity\Loan;
use App\Entity\Property;
use App\Entity\Space;
use Doctrine\Persistence\ObjectRepository;

/**
 * @extends ObjectRepository<Loan>
 */
interface LoanRepositoryInterface extends ObjectRepository
{
    /** @return Loan[] loans of the space, most recent first */
    public function findBySpace(Space $space): array;

    /** @return Loan[] loans funding the property, most recent first */
    public function findByProperty(Property $property): array;

    /** Count the loans funding the property — a funded property cannot be deleted. */
    public function countByProperty(Property $property): int;
}
