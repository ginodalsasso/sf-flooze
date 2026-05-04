<?php

declare(strict_types=1);

namespace App\Service\Finance;

use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;

class CategoryService
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function save(Category $category): void
    {
        $this->em->persist($category);
        $this->em->flush();
    }

    /** Promotes children to the deleted category's parent before removing. */
    public function delete(Category $category): void
    {
        foreach ($category->getChildren() as $child) {
            $child->setParent($category->getParent());
        }

        $this->em->remove($category);
        $this->em->flush();
    }
}
