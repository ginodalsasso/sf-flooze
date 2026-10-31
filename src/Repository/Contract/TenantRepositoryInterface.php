<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Entity\Space;
use App\Entity\Tenant;
use Doctrine\Persistence\ObjectRepository;

/**
 * @extends ObjectRepository<Tenant>
 */
interface TenantRepositoryInterface extends ObjectRepository
{
    /** @return Tenant[] tenants for the space, ordered by last name */
    public function findBySpace(Space $space): array;
}
