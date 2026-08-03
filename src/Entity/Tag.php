<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TagRepository;
use App\Trait\SpaceScopeTrait;
use App\Trait\TimestampTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * Cross-cutting label on a transaction (projet, événement, personne).
**/
#[ORM\Entity(repositoryClass: TagRepository::class)]
#[ORM\Table(name: 'tag')]
#[ORM\UniqueConstraint(name: 'uniq_tag_space_name', columns: ['space_id', 'name'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['space', 'name'], message: 'Ce tag existe déjà dans cet espace.', errorPath: 'name')]
class Tag
{
    use TimestampTrait;
    use SpaceScopeTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private string $name;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }
}
