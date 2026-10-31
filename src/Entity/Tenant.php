<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TenantRepository;
use App\Trait\SpaceScopeTrait;
use App\Trait\TimestampTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TenantRepository::class)]
#[ORM\Table(name: 'tenant')]
#[ORM\HasLifecycleCallbacks]
class Tenant
{
    use TimestampTrait;
    use SpaceScopeTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'first_name', length: 100)]
    private string $firstName;

    #[ORM\Column(name: 'last_name', length: 100)]
    private string $lastName;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $phone = null;

    /** Solvency evidence kept on file: the rent-to-income ratio justifies the lease. */
    #[ORM\Column(name: 'monthly_income', type: 'decimal', precision: 15, scale: 2)]
    private string $monthlyIncome = '0.00';

    #[ORM\Column(name: 'guarantor_name', length: 200, nullable: true)]
    private ?string $guarantorName = null;

    #[ORM\Column(name: 'guarantor_income', type: 'decimal', precision: 15, scale: 2, nullable: true)]
    private ?string $guarantorIncome = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getMonthlyIncome(): string
    {
        return $this->monthlyIncome;
    }

    public function setMonthlyIncome(string $monthlyIncome): static
    {
        $this->monthlyIncome = $monthlyIncome;

        return $this;
    }

    public function getGuarantorName(): ?string
    {
        return $this->guarantorName;
    }

    public function setGuarantorName(?string $guarantorName): static
    {
        $this->guarantorName = $guarantorName;

        return $this;
    }

    public function getGuarantorIncome(): ?string
    {
        return $this->guarantorIncome;
    }

    public function setGuarantorIncome(?string $guarantorIncome): static
    {
        $this->guarantorIncome = $guarantorIncome;

        return $this;
    }
}
