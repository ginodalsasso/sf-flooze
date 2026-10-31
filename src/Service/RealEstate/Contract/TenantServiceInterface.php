<?php

declare(strict_types=1);

namespace App\Service\RealEstate\Contract;

use App\Entity\Tenant;

/** Tenant persistence. A tenant has no soft-delete: it is kept or removed outright. */
interface TenantServiceInterface
{
    public function save(Tenant $tenant): void;

    /** @throws \RuntimeException when the tenant is still bound to a lease */
    public function delete(Tenant $tenant): void;
}
