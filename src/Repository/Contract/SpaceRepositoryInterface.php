<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Entity\Space;
use Doctrine\Persistence\ObjectRepository;

/**
 * Space access point. No custom query yet: the Doctrine contract is the whole API.
 *
 * @extends ObjectRepository<Space>
 */
interface SpaceRepositoryInterface extends ObjectRepository
{
}
