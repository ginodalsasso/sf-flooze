<?php

declare(strict_types=1);

namespace App\Service\RealEstate;

use App\Entity\LoanLine;
use App\Service\RealEstate\Contract\LoanLineServiceInterface;
use Doctrine\ORM\EntityManagerInterface;

final class LoanLineService implements LoanLineServiceInterface
{
    /** Beyond this, a duration is a typo rather than a mortgage. */
    private const int MAX_DURATION_MONTHS = 600;

    public function __construct(private readonly EntityManagerInterface $em) {}

    public function save(LoanLine $line): void
    {
        $this->guardSameSpace($line);
        $this->guardConsistentTerms($line);

        $this->em->persist($line);
        $this->em->flush();
    }

    public function delete(LoanLine $line): void
    {
        $this->em->remove($line);
        $this->em->flush();
    }

    /** A line is reached through its loan, whose space is the only one it may belong to. */
    private function guardSameSpace(LoanLine $line): void
    {
        if ($line->getLoan()->getSpace()->getId() !== $line->getSpace()->getId()) {
            throw new \InvalidArgumentException('Élément hors de cet espace : emprunt.');
        }
    }

    /** A schedule is only as sound as its terms: nonsense here would produce a plausible table. */
    private function guardConsistentTerms(LoanLine $line): void
    {
        if (bccomp($line->getAmount(), '0.00', 2) <= 0) {
            throw new \InvalidArgumentException('Le capital de la ligne doit être strictement positif.');
        }

        if ($line->getDurationMonths() < 1) {
            throw new \InvalidArgumentException('La durée doit valoir au moins 1 mois.');
        }

        if ($line->getDurationMonths() > self::MAX_DURATION_MONTHS) {
            throw new \InvalidArgumentException(sprintf(
                'La durée ne peut pas dépasser %d mois.',
                self::MAX_DURATION_MONTHS,
            ));
        }

        if (bccomp($line->getRate(), '0.0000', 4) < 0 || bccomp($line->getInsuranceRate(), '0.0000', 4) < 0) {
            throw new \InvalidArgumentException('Un taux ne peut pas être négatif.');
        }
    }
}
