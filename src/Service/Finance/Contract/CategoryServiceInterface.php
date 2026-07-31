<?php

declare(strict_types=1);

namespace App\Service\Finance\Contract;

use App\Entity\Category;

/** Category persistence and hierarchy-safe deletion. */
interface CategoryServiceInterface
{
    public function save(Category $category): void;

    /** Promotes children to the deleted category's parent before removing. */
    public function delete(Category $category): void;
}
