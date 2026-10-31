<?php

declare(strict_types=1);

namespace App\Service\RealEstate\Contract;

use App\Entity\Property;

/** Property persistence and soft-delete. */
interface PropertyServiceInterface
{
    public function save(Property $property): void;

    /** @throws \RuntimeException when a lease is still running on the property */
    public function delete(Property $property): void;
}
