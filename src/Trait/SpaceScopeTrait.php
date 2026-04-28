<?php

declare(strict_types=1); // strict types for better type safety

namespace App\Trait;

use App\Entity\Space;
use Doctrine\ORM\Mapping as ORM;

trait SpaceScopeTrait
{
    #[ORM\ManyToOne(targetEntity: Space::class)]
    #[ORM\JoinColumn(name: 'space_id', nullable: false)]
    private Space $space;

    public function getSpace(): Space
    {
        return $this->space;
    }

    public function setSpace(Space $space): static
    {
        $this->space = $space;

        return $this;
    }
}
