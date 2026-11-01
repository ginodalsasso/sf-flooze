<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\FiscalBucketEnum;
use App\Enum\FiscalRoleEnum;
use App\Enum\TransactionTypeEnum;
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

    /**
     * Catégorie de revenus à laquelle cette catégorie se rattache. Null = aucune portée fiscale.
     *
     * C'est ce qui cloisonne la déclaration : une charge ne se déduit que des revenus de sa
     * propre catégorie. Un booléen « déductible » ne pouvait pas dire de quoi.
     */
    #[ORM\Column(name: 'fiscal_bucket', type: 'string', enumType: FiscalBucketEnum::class, nullable: true)]
    private ?FiscalBucketEnum $fiscalBucket = null;

    /** Sens dans cette catégorie fiscale : revenu ou charge. Null = aucune portée fiscale. */
    #[ORM\Column(name: 'fiscal_role', type: 'string', enumType: FiscalRoleEnum::class, nullable: true)]
    private ?FiscalRoleEnum $fiscalRole = null;

    /** @var list<string> TransactionTypeEnum values this category can be used on; empty = all types */
    #[ORM\Column(type: 'json')]
    private array $applicableTypes = [];

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

    public function getFiscalBucket(): ?FiscalBucketEnum
    {
        return $this->fiscalBucket;
    }

    public function setFiscalBucket(?FiscalBucketEnum $fiscalBucket): static
    {
        $this->fiscalBucket = $fiscalBucket;

        return $this;
    }

    public function getFiscalRole(): ?FiscalRoleEnum
    {
        return $this->fiscalRole;
    }

    public function setFiscalRole(?FiscalRoleEnum $fiscalRole): static
    {
        $this->fiscalRole = $fiscalRole;

        return $this;
    }

    /** Une catégorie n'entre dans la déclaration que si son rattachement est complet. */
    public function hasFiscalScope(): bool
    {
        return $this->fiscalBucket !== null && $this->fiscalRole !== null;
    }

    /** Un rôle posé sans catégorie fiscale : la saisie est à terminer, rien n'est compté. */
    public function hasIncompleteFiscalScope(): bool
    {
        return $this->fiscalBucket === null && $this->fiscalRole !== null;
    }

    /** @return list<TransactionTypeEnum> */
    public function getApplicableTypes(): array
    {
        return array_map(TransactionTypeEnum::from(...), $this->applicableTypes);
    }

    /** @param list<TransactionTypeEnum> $types */
    public function setApplicableTypes(array $types): static
    {
        $this->applicableTypes = array_values(array_unique(
            array_map(fn(TransactionTypeEnum $type) => $type->value, $types)
        ));

        return $this;
    }

    /** True when the category may be used on a transaction of this type (empty = all types) 
     * ex: $category->appliesTo(TransactionTypeEnum::EXPENSE) will return true if the category can be used on an expense transaction.
    */
    public function appliesTo(TransactionTypeEnum $type): bool
    {
        return $this->applicableTypes === [] || in_array($type->value, $this->applicableTypes, true);
    }
}
