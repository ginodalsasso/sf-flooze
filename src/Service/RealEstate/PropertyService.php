<?php

declare(strict_types=1);

namespace App\Service\RealEstate;

use App\Entity\Property;
use App\Repository\Contract\LeaseRepositoryInterface;
use App\Repository\Contract\LoanRepositoryInterface;
use App\Service\RealEstate\Contract\PropertyServiceInterface;
use Doctrine\ORM\EntityManagerInterface;

final class PropertyService implements PropertyServiceInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LeaseRepositoryInterface $leaseRepository,
        private readonly LoanRepositoryInterface $loanRepository,
    ) {}

    public function save(Property $property): void
    {
        $this->em->persist($property);
        $this->em->flush();
    }

    /** A running lease or a loan would be orphaned, losing the subject they are attached to. */
    public function delete(Property $property): void
    {
        if ($this->leaseRepository->countActiveByProperty($property) > 0) {
            throw new \RuntimeException('Ce bien porte encore un bail actif : clôture le bail avant de le supprimer.');
        }

        if ($this->loanRepository->countByProperty($property) > 0) {
            throw new \RuntimeException('Ce bien porte encore un emprunt : supprime l\'emprunt avant de supprimer le bien.');
        }

        $property->softDelete();
        $this->em->flush();
    }
}
