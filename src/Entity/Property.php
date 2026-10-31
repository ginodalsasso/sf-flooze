<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\PropertyTypeEnum;
use App\Repository\PropertyRepository;
use App\Trait\SoftDeleteTrait;
use App\Trait\SpaceScopeTrait;
use App\Trait\TimestampTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PropertyRepository::class)]
#[ORM\Table(name: 'property')]
#[ORM\HasLifecycleCallbacks]
class Property
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

    #[ORM\Column(length: 255)]
    private string $address;

    #[ORM\Column(type: 'string', enumType: PropertyTypeEnum::class)]
    private PropertyTypeEnum $type;

    #[ORM\Column(name: 'purchase_price', type: 'decimal', precision: 15, scale: 2)]
    private string $purchasePrice = '0.00';

    #[ORM\Column(name: 'purchase_date', type: 'date_immutable')]
    private \DateTimeImmutable $purchaseDate;

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

    public function getAddress(): string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getType(): PropertyTypeEnum
    {
        return $this->type;
    }

    public function setType(PropertyTypeEnum $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getPurchasePrice(): string
    {
        return $this->purchasePrice;
    }

    public function setPurchasePrice(string $purchasePrice): static
    {
        $this->purchasePrice = $purchasePrice;

        return $this;
    }

    public function getPurchaseDate(): \DateTimeImmutable
    {
        return $this->purchaseDate;
    }

    public function setPurchaseDate(\DateTimeImmutable $purchaseDate): static
    {
        $this->purchaseDate = $purchaseDate;

        return $this;
    }
}
