<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AssetTypeEnum;
use App\Repository\AssetRepository;
use App\Trait\SpaceScopeTrait;
use App\Trait\TimestampTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AssetRepository::class)]
#[ORM\Table(name: 'asset')]
#[ORM\HasLifecycleCallbacks]
class Asset
{
    use TimestampTrait;
    use SpaceScopeTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private string $ticker;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column(type: 'decimal', precision: 18, scale: 8)]
    private string $quantity;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 4)]
    private string $avgPrice;

    #[ORM\Column(length: 3)]
    private string $currency = 'EUR';

    #[ORM\Column(type: 'string', enumType: AssetTypeEnum::class)]
    private AssetTypeEnum $type;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTicker(): string
    {
        return $this->ticker;
    }

    public function setTicker(string $ticker): static
    {
        $this->ticker = strtoupper($ticker);

        return $this;
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

    public function getQuantity(): string
    {
        return $this->quantity;
    }

    public function setQuantity(string $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getAvgPrice(): string
    {
        return $this->avgPrice;
    }

    public function setAvgPrice(string $avgPrice): static
    {
        $this->avgPrice = $avgPrice;

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getType(): AssetTypeEnum
    {
        return $this->type;
    }

    public function setType(AssetTypeEnum $type): static
    {
        $this->type = $type;

        return $this;
    }

    /** Total cost basis = quantity × avg_price */
    public function getTotalCost(): float
    {
        return (float) $this->quantity * (float) $this->avgPrice;
    }
}
