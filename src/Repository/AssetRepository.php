<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Asset;
use App\Entity\Space;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Asset>
 */
class AssetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Asset::class);
    }

    /** @return Asset[] assets for the space, ordered by type then ticker */
    public function findBySpace(Space $space): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.space = :space')
            ->setParameter('space', $space)
            ->orderBy('a.type', 'ASC')
            ->addOrderBy('a.ticker', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
