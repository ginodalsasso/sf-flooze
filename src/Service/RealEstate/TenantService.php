<?php

declare(strict_types=1);

namespace App\Service\RealEstate;

use App\Entity\Tenant;
use App\Repository\Contract\LeaseRepositoryInterface;
use App\Service\RealEstate\Contract\TenantServiceInterface;
use Doctrine\ORM\EntityManagerInterface;

final class TenantService implements TenantServiceInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LeaseRepositoryInterface $leaseRepository,
    ) {}

    public function save(Tenant $tenant): void
    {
        $this->em->persist($tenant);
        $this->em->flush();
    }

    /** Removing a bound tenant would silently empty the lease it signed. */
    public function delete(Tenant $tenant): void
    {
        if ($this->leaseRepository->countByTenant($tenant) > 0) {
            throw new \RuntimeException('Ce locataire est rattaché à un bail : détache-le du bail avant de le supprimer.');
        }

        $this->em->remove($tenant);
        $this->em->flush();
    }
}
