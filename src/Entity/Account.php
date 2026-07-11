<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AccountTypeEnum;
use App\Enum\CurrencyEnum;
use App\Repository\AccountRepository;
use App\Trait\SoftDeleteTrait;
use App\Trait\SpaceScopeTrait;
use App\Trait\TimestampTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AccountRepository::class)]
#[ORM\Table(name: 'account')]
#[ORM\HasLifecycleCallbacks]
class Account
{
    use TimestampTrait;
    use SpaceScopeTrait;
    use SoftDeleteTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column(type: 'string', enumType: AccountTypeEnum::class)]
    private AccountTypeEnum $type;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    private string $balance = '0.00';

    #[ORM\Column(type: 'string', enumType: CurrencyEnum::class)]
    private CurrencyEnum $currency;

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

    public function getType(): AccountTypeEnum
    {
        return $this->type;
    }

    public function setType(AccountTypeEnum $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getBalance(): string
    {
        return $this->balance;
    }

    public function setBalance(string $balance): static
    {
        $this->balance = $balance;

        return $this;
    }

    public function getCurrency(): CurrencyEnum
    {
        return $this->currency;
    }

    public function setCurrency(CurrencyEnum $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * Add an amount (positive or negative) to the current balance.
     *
     * The amount must be a numeric string so that BC math keeps 2-decimal
     * precision. This is the single source of truth for balance updates.
     */
    public function adjustBalance(string $amount): static
    {
        // bcadd: add numeric strings, scale 2 keeps cents precision.
        $this->balance = bcadd($this->balance, $amount, 2);

        return $this;
    }
}
