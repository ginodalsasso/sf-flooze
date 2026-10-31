<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Property;
use App\Entity\Space;
use App\Repository\Contract\PropertyRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Property>
 */
final class PropertyRepository extends ServiceEntityRepository implements PropertyRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Property::class);
    }

    /** @return Property[] active properties for the space, ordered by name */
    public function findBySpace(Space $space): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.space = :space')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('space', $space)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Count active properties for the space. */
    public function countBySpace(Space $space): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.space = :space')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('space', $space)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
