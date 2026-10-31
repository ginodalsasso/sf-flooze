<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Lease;
use App\Entity\Property;
use App\Entity\Space;
use App\Entity\Tenant;
use App\Repository\Contract\LeaseRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Lease>
 */
final class LeaseRepository extends ServiceEntityRepository implements LeaseRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Lease::class);
    }

    /** @return Lease[] active leases for the space, most recent first */
    public function findBySpace(Space $space): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.space = :space')
            ->andWhere('l.deletedAt IS NULL')
            ->setParameter('space', $space)
            ->orderBy('l.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return Lease[] active leases of the property, most recent first */
    public function findByProperty(Property $property): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.property = :property')
            ->andWhere('l.space = :space')
            ->andWhere('l.deletedAt IS NULL')
            ->setParameter('property', $property)
            ->setParameter('space', $property->getSpace())
            ->orderBy('l.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count the leases a tenant is bound to — a bound tenant cannot be deleted.
     *
     * MEMBER OF, not a join: a ManyToMany join duplicates rows and would inflate the count.
     */
    public function countByTenant(Tenant $tenant): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where(':tenant MEMBER OF l.tenants')
            ->andWhere('l.space = :space')
            ->andWhere('l.deletedAt IS NULL')
            ->setParameter('tenant', $tenant)
            ->setParameter('space', $tenant->getSpace())
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** Count leases still running on the property — a property with one cannot be deleted. */
    public function countActiveByProperty(Property $property): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.property = :property')
            ->andWhere('l.space = :space')
            ->andWhere('l.deletedAt IS NULL')
            ->andWhere('l.isActive = true')
            ->setParameter('property', $property)
            ->setParameter('space', $property->getSpace())
            ->getQuery()
            ->getSingleScalarResult();
    }
}
