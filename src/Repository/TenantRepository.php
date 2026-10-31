<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Space;
use App\Entity\Tenant;
use App\Repository\Contract\TenantRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tenant>
 */
final class TenantRepository extends ServiceEntityRepository implements TenantRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tenant::class);
    }

    /** @return Tenant[] tenants for the space, ordered by last name */
    public function findBySpace(Space $space): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.space = :space')
            ->setParameter('space', $space)
            ->orderBy('t.lastName', 'ASC')
            ->addOrderBy('t.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
