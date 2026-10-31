<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Entity\Lease;
use App\Entity\Property;
use App\Entity\Space;
use App\Entity\Tenant;
use Doctrine\Persistence\ObjectRepository;

/**
 * @extends ObjectRepository<Lease>
 */
interface LeaseRepositoryInterface extends ObjectRepository
{
    /** @return Lease[] active leases for the space, most recent first */
    public function findBySpace(Space $space): array;

    /** @return Lease[] active leases of the property, most recent first */
    public function findByProperty(Property $property): array;

    /** Count the leases a tenant is bound to — a bound tenant cannot be deleted. */
    public function countByTenant(Tenant $tenant): int;

    /** Count leases still running on the property — a property with one cannot be deleted. */
    public function countActiveByProperty(Property $property): int;
}
