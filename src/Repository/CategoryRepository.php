<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Category;
use App\Entity\Space;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    /** @return Category[] root categories (no parent) ordered by name */
    public function findRootsBySpace(Space $space): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.space = :space')
            ->andWhere('c.parent IS NULL')
            ->setParameter('space', $space)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return Category[] all categories for space, ordered by name */
    public function findBySpace(Space $space): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.space = :space')
            ->setParameter('space', $space)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** QueryBuilder scoped to space (used by form EntityType) */
    public function createSpaceScopedQb(Space $space): QueryBuilder
    {
        return $this->createQueryBuilder('c')
            ->where('c.space = :space')
            ->setParameter('space', $space)
            ->orderBy('c.name', 'ASC');
    }
}
