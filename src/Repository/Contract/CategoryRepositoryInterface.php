<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Entity\Category;
use App\Entity\Space;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ObjectRepository;

/**
 * @extends ObjectRepository<Category>
 */
interface CategoryRepositoryInterface extends ObjectRepository
{
    /** @return Category[] root categories (no parent) ordered by name */
    public function findRootsBySpace(Space $space): array;

    /** @return Category[] all categories for space, ordered by name */
    public function findBySpace(Space $space): array;

    /** QueryBuilder scoped to space (used by form EntityType) */
    public function createSpaceScopedQb(Space $space): QueryBuilder;
}
