<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Entity\Space;
use App\Entity\Tag;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ObjectRepository;

/**
 * @extends ObjectRepository<Tag>
 */
interface TagRepositoryInterface extends ObjectRepository
{
    /** @return Tag[] all tags for space, ordered by name */
    public function findBySpace(Space $space): array;

    /** QueryBuilder scoped to space (used by form EntityType) */
    public function createSpaceScopedQb(Space $space): QueryBuilder;
}
