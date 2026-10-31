<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Loan;
use App\Entity\Property;
use App\Entity\Space;
use App\Repository\Contract\LoanRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Loan>
 */
final class LoanRepository extends ServiceEntityRepository implements LoanRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Loan::class);
    }

    /**
     * Loans of the space, most recent first. A loan has no soft delete (ERD), so only the
     * property it funds can hide it.
     *
     * @return Loan[]
     */
    public function findBySpace(Space $space): array
    {
        return $this->createQueryBuilder('l')
            ->join('l.property', 'p')
            ->where('l.space = :space')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('space', $space)
            ->orderBy('l.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return Loan[] loans funding the property, most recent first */
    public function findByProperty(Property $property): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.property = :property')
            ->andWhere('l.space = :space')
            ->setParameter('property', $property)
            ->setParameter('space', $property->getSpace())
            ->orderBy('l.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** Count the loans funding the property — a funded property cannot be deleted. */
    public function countByProperty(Property $property): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.property = :property')
            ->andWhere('l.space = :space')
            ->setParameter('property', $property)
            ->setParameter('space', $property->getSpace())
            ->getQuery()
            ->getSingleScalarResult();
    }
}
