<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Entity\Property;
use App\Entity\Space;
use Doctrine\Persistence\ObjectRepository;

/**
 * @extends ObjectRepository<Property>
 */
interface PropertyRepositoryInterface extends ObjectRepository
{
    /** @return Property[] active properties for the space, ordered by name */
    public function findBySpace(Space $space): array;

    /** Count active properties for the space. */
    public function countBySpace(Space $space): int;
}
