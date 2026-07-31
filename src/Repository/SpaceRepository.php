<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Space;
use App\Repository\Contract\SpaceRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Space>
 */
final class SpaceRepository extends ServiceEntityRepository implements SpaceRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Space::class);
    }
}
