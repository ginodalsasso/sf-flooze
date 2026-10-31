<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\LeaseTypeEnum;
use App\Repository\LeaseRepository;
use App\Trait\SoftDeleteTrait;
use App\Trait\SpaceScopeTrait;
use App\Trait\TimestampTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LeaseRepository::class)]
#[ORM\Table(name: '`lease`')]
#[ORM\HasLifecycleCallbacks]
class Lease
{
    use TimestampTrait;
    use SpaceScopeTrait;
    use SoftDeleteTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Property::class)]
    #[ORM\JoinColumn(name: 'property_id', nullable: false)]
    private Property $property;

    /** Tenants hold the lease jointly — a colocation binds several of them to the same rent. */
    #[ORM\ManyToMany(targetEntity: Tenant::class)]
    #[ORM\JoinTable(name: 'lease_tenant')]
    #[ORM\JoinColumn(name: 'lease_id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'tenant_id', onDelete: 'CASCADE')]
    private Collection $tenants;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    private string $rent = '0.00';

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    private string $charges = '0.00';

    #[ORM\Column(type: 'string', enumType: LeaseTypeEnum::class)]
    private LeaseTypeEnum $type;

    #[ORM\Column(name: 'security_deposit', type: 'decimal', precision: 15, scale: 2)]
    private string $securityDeposit = '0.00';

    #[ORM\Column(name: 'start_date', type: 'date_immutable')]
    private \DateTimeImmutable $startDate;

    #[ORM\Column(name: 'end_date', type: 'date_immutable')]
    private \DateTimeImmutable $endDate;

    /** A lease stops producing rent due dates once inactive, without being deleted. */
    #[ORM\Column(name: 'is_active', type: 'boolean')]
    private bool $isActive = true;

    public function __construct()
    {
        $this->tenants = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProperty(): Property
    {
        return $this->property;
    }

    public function setProperty(Property $property): static
    {
        $this->property = $property;

        return $this;
    }

    /** @return Collection<int, Tenant> */
    public function getTenants(): Collection
    {
        return $this->tenants;
    }

    public function addTenant(Tenant $tenant): static
    {
        if (!$this->tenants->contains($tenant)) {
            $this->tenants->add($tenant);
        }

        return $this;
    }

    public function removeTenant(Tenant $tenant): static
    {
        $this->tenants->removeElement($tenant);

        return $this;
    }

    public function getRent(): string
    {
        return $this->rent;
    }

    public function setRent(string $rent): static
    {
        $this->rent = $rent;

        return $this;
    }

    public function getCharges(): string
    {
        return $this->charges;
    }

    public function setCharges(string $charges): static
    {
        $this->charges = $charges;

        return $this;
    }

    public function getType(): LeaseTypeEnum
    {
        return $this->type;
    }

    public function setType(LeaseTypeEnum $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getSecurityDeposit(): string
    {
        return $this->securityDeposit;
    }

    public function setSecurityDeposit(string $securityDeposit): static
    {
        $this->securityDeposit = $securityDeposit;

        return $this;
    }

    public function getStartDate(): \DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): \DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }
}
