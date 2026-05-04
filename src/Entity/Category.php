<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CategoryRepository;
use App\Trait\SpaceScopeTrait;
use App\Trait\TimestampTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
#[ORM\Table(name: 'category')]
#[ORM\HasLifecycleCallbacks]
class Category
{
    use TimestampTrait;
    use SpaceScopeTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', nullable: true)]
    private ?Category $parent = null;

    /** @var Collection<int, Category> */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    private Collection $children;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column(type: 'boolean')]
    private bool $isDeductible = false;

    #[ORM\Column(type: 'boolean')]
    private bool $isDeclarable = false;

    public function __construct()
    {
        $this->children = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParent(): ?Category
    {
        return $this->parent;
    }

    public function setParent(?Category $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    /** @return Collection<int, Category> */
    public function getChildren(): Collection
    {
        return $this->children;
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

    public function isDeductible(): bool
    {
        return $this->isDeductible;
    }

    public function setIsDeductible(bool $isDeductible): static
    {
        $this->isDeductible = $isDeductible;

        return $this;
    }

    public function isDeclarable(): bool
    {
        return $this->isDeclarable;
    }

    public function setIsDeclarable(bool $isDeclarable): static
    {
        $this->isDeclarable = $isDeclarable;

        return $this;
    }
}
