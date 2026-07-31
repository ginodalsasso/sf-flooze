<?php

declare(strict_types=1);

namespace App\Service\Finance;

use App\Entity\Category;
use App\Service\Finance\Contract\CategoryServiceInterface;
use Doctrine\ORM\EntityManagerInterface;

final class CategoryService implements CategoryServiceInterface
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function save(Category $category): void
    {
        $this->em->persist($category);
        $this->em->flush();
    }

    public function delete(Category $category): void
    {
        foreach ($category->getChildren() as $child) {
            $child->setParent($category->getParent());
        }

        $this->em->remove($category);
        $this->em->flush();
    }
}
