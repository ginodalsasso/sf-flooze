<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AssetEntryKindEnum;
use App\Repository\AssetEntryRepository;
use App\Trait\SpaceScopeTrait;
use App\Trait\TimestampTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AssetEntryRepository::class)]
#[ORM\Table(name: 'asset_entry')]
#[ORM\HasLifecycleCallbacks]
class AssetEntry
{
    use TimestampTrait;
    use SpaceScopeTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Asset::class, inversedBy: 'entries')]
    #[ORM\JoinColumn(name: 'asset_id', nullable: false)]
    private Asset $asset;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $date;

    #[ORM\Column(type: 'string', enumType: AssetEntryKindEnum::class)]
    private AssetEntryKindEnum $kind;

    #[ORM\Column(type: 'decimal', precision: 18, scale: 8)]
    private string $quantity;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 4)]
    private string $unitPrice;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 6)]
    private string $fxRate;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    private string $fees;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(name: 'account_id', nullable: true)]
    private ?Account $account = null;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(name: 'funding_account_id', nullable: true)]
    private ?Account $fundingAccount = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $note = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAsset(): Asset
    {
        return $this->asset;
    }

    public function setAsset(Asset $asset): static
    {
        $this->asset = $asset;

        return $this;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getKind(): AssetEntryKindEnum
    {
        return $this->kind;
    }

    public function setKind(AssetEntryKindEnum $kind): static
    {
        $this->kind = $kind;

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

    public function getUnitPrice(): string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(string $unitPrice): static
    {
        $this->unitPrice = $unitPrice;

        return $this;
    }

    public function getFxRate(): string
    {
        return $this->fxRate;
    }

    public function setFxRate(string $fxRate): static
    {
        $this->fxRate = $fxRate;

        return $this;
    }

    public function getFees(): string
    {
        return $this->fees;
    }

    public function setFees(string $fees): static
    {
        $this->fees = $fees;

        return $this;
    }

    public function getAccount(): ?Account
    {
        return $this->account;
    }

    public function setAccount(?Account $account): static
    {
        $this->account = $account;

        return $this;
    }

    public function getFundingAccount(): ?Account
    {
        return $this->fundingAccount;
    }

    public function setFundingAccount(?Account $fundingAccount): static
    {
        $this->fundingAccount = $fundingAccount;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }

    /** Total amount of this entry in asset currency: qty × unit_price */
    public function getTotalAmount(): float
    {
        return (float) $this->quantity * (float) $this->unitPrice;
    }

    /** Total amount converted to space currency: qty × unit_price × fx_rate */
    public function getTotalAmountInSpaceCurrency(): float
    {
        return $this->getTotalAmount() * (float) $this->fxRate;
    }

    /** Net amount after fees in space currency */
    public function getNetAmount(): float
    {
        $total = $this->getTotalAmountInSpaceCurrency();

        return $this->kind->isCashInflow()
            ? $total - (float) $this->fees
            : $total + (float) $this->fees;
    }
}
