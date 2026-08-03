<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Space;
use App\Entity\Tag;
use App\Repository\Contract\TagRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

final class TagRepository extends ServiceEntityRepository implements TagRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

    /** @return Tag[] all tags for space, ordered by name */
    public function findBySpace(Space $space): array
    {
        return $this->createSpaceScopedQb($space)
            ->getQuery()
            ->getResult();
    }

    /** QueryBuilder scoped to space (used by form EntityType) */
    public function createSpaceScopedQb(Space $space): QueryBuilder
    {
        return $this->createQueryBuilder('tg')
            ->where('tg.space = :space')
            ->setParameter('space', $space)
            ->orderBy('tg.name', 'ASC');
    }
}
