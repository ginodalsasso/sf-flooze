<?php

declare(strict_types=1);

namespace App\Service\RealEstate;

use App\Entity\Loan;
use App\Service\RealEstate\Contract\LoanServiceInterface;
use Doctrine\ORM\EntityManagerInterface;

final class LoanService implements LoanServiceInterface
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function save(Loan $loan): void
    {
        $this->guardSameSpace($loan);

        $this->em->persist($loan);
        $this->em->flush();
    }

    /** Lines cascade: removing the file removes the schedule it carried. */
    public function delete(Loan $loan): void
    {
        $this->em->remove($loan);
        $this->em->flush();
    }

    /** The form scopes its choices, but an ID posted by hand proves nothing about its owner. */
    private function guardSameSpace(Loan $loan): void
    {
        if ($loan->getProperty()->getSpace()->getId() !== $loan->getSpace()->getId()) {
            throw new \InvalidArgumentException('Élément hors de cet espace : bien.');
        }
    }
}
