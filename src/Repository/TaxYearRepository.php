<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Space;
use App\Entity\TaxYear;
use App\Repository\Contract\TaxYearRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaxYear>
 */
final class TaxYearRepository extends ServiceEntityRepository implements TaxYearRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaxYear::class);
    }

    public function findBySpaceAndYear(Space $space, int $year): ?TaxYear
    {
        return $this->createQueryBuilder('ty')
            ->where('ty.space = :space')
            ->andWhere('ty.year = :year')
            ->setParameter('space', $space)
            ->setParameter('year', $year)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findBySpace(Space $space): array
    {
        return $this->createQueryBuilder('ty')
            ->where('ty.space = :space')
            ->setParameter('space', $space)
            ->orderBy('ty.year', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
