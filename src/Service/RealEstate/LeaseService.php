<?php

declare(strict_types=1);

namespace App\Service\RealEstate;

use App\Entity\Lease;
use App\Service\RealEstate\Contract\LeaseServiceInterface;
use Doctrine\ORM\EntityManagerInterface;

final class LeaseService implements LeaseServiceInterface
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function save(Lease $lease): void
    {
        $this->guardSameSpace($lease);
        $this->guardConsistentTerm($lease);
        $this->guardRentableProperty($lease);

        $this->em->persist($lease);
        $this->em->flush();
    }

    public function delete(Lease $lease): void
    {
        $lease->softDelete();
        $this->em->flush();
    }

    public function toggleActive(Lease $lease): void
    {
        $lease->setIsActive(!$lease->isActive());
        $this->em->flush();
    }

    /** The form scopes its choices, but an ID posted by hand proves nothing about its owner. */
    private function guardSameSpace(Lease $lease): void
    {
        $owners = ['bien' => $lease->getProperty()->getSpace()];

        foreach ($lease->getTenants() as $tenant) {
            $owners['locataire « ' . $tenant->getFullName() . ' »'] = $tenant->getSpace();
        }

        foreach ($owners as $label => $owner) {
            if ($owner->getId() !== $lease->getSpace()->getId()) {
                throw new \InvalidArgumentException(sprintf('Élément hors de cet espace : %s.', $label));
            }
        }
    }

    private function guardConsistentTerm(Lease $lease): void
    {
        if ($lease->getEndDate() < $lease->getStartDate()) {
            throw new \InvalidArgumentException('La date de fin du bail ne peut pas précéder sa date de début.');
        }

        if (bccomp($lease->getRent(), '0.00', 2) <= 0) {
            throw new \InvalidArgumentException('Le loyer doit être strictement positif.');
        }

        if (bccomp($lease->getCharges(), '0.00', 2) < 0 || bccomp($lease->getSecurityDeposit(), '0.00', 2) < 0) {
            throw new \InvalidArgumentException('Les charges et le dépôt de garantie ne peuvent pas être négatifs.');
        }
    }

    private function guardRentableProperty(Lease $lease): void
    {
        if (!$lease->getProperty()->getType()->isRentable()) {
            throw new \InvalidArgumentException('Une résidence principale ne peut pas porter de bail.');
        }
    }
}
