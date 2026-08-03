<?php

declare(strict_types=1);

namespace App\Service\Finance;

use App\Entity\Tag;
use App\Service\Finance\Contract\TagServiceInterface;
use Doctrine\ORM\EntityManagerInterface;

final class TagService implements TagServiceInterface
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function save(Tag $tag): void
    {
        $this->em->persist($tag);
        $this->em->flush();
    }

    public function delete(Tag $tag): void
    {
        $this->em->remove($tag);
        $this->em->flush();
    }
}
