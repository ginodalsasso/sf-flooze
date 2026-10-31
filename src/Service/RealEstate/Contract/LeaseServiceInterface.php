<?php

declare(strict_types=1);

namespace App\Service\RealEstate\Contract;

use App\Entity\Lease;

/** Lease persistence, soft-delete and activity toggle. */
interface LeaseServiceInterface
{
    /** @throws \InvalidArgumentException when the lease is inconsistent or crosses a space boundary */
    public function save(Lease $lease): void;

    public function delete(Lease $lease): void;

    /** Closes or reopens the lease: an inactive lease stops producing rent due dates. */
    public function toggleActive(Lease $lease): void;
}
