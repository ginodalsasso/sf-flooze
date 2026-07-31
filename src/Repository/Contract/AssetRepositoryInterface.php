<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Entity\Asset;
use App\Entity\Space;
use Doctrine\Persistence\ObjectRepository;

/**
 * @extends ObjectRepository<Asset>
 */
interface AssetRepositoryInterface extends ObjectRepository
{
    /** @return Asset[] assets for the space, ordered by type then ticker */
    public function findBySpace(Space $space): array;
}
