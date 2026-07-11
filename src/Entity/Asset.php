<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AssetEntryKindEnum;
use App\Enum\AssetTypeEnum;
use App\Enum\CurrencyEnum;
use App\Repository\AssetRepository;
use App\Trait\SpaceScopeTrait;
use App\Trait\TimestampTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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

    #[ORM\Column(type: 'string', enumType: CurrencyEnum::class)]
    private CurrencyEnum $currency;

    #[ORM\Column(type: 'string', enumType: AssetTypeEnum::class)]
    private AssetTypeEnum $type;

    /** @var Collection<int, AssetEntry> */
    #[ORM\OneToMany(targetEntity: AssetEntry::class, mappedBy: 'asset', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['date' => 'DESC', 'createdAt' => 'DESC'])]
    private Collection $entries;

    public function __construct()
    {
        $this->entries = new ArrayCollection();
    }

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

    public function getCurrency(): CurrencyEnum
    {
        return $this->currency;
    }

    public function setCurrency(CurrencyEnum $currency): static
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

    /** @return Collection<int, AssetEntry> */
    public function getEntries(): Collection
    {
        return $this->entries;
    }

    public function addEntry(AssetEntry $entry): static
    {
        if (!$this->entries->contains($entry)) {
            $this->entries->add($entry);
            $entry->setAsset($this);
        }

        return $this;
    }

    public function removeEntry(AssetEntry $entry): static
    {
        $this->entries->removeElement($entry);

        return $this;
    }

    /** Total quantity held: sum(buy) - sum(sell) */
    public function getTotalQuantity(): float
    {
        $total = 0.0;
        foreach ($this->entries as $entry) {
            $total += (float) $entry->getQuantity() * $entry->getKind()->quantitySign();
        }

        return $total;
    }

    /** Weighted average purchase price in asset currency */
    public function getAvgPrice(): ?string
    {
        $totalQty = 0.0;
        $totalCost = 0.0;

        foreach ($this->entries as $entry) {
            if ($entry->getKind() === AssetEntryKindEnum::Buy) {
                $qty = (float) $entry->getQuantity();
                $totalQty += $qty;
                $totalCost += $qty * (float) $entry->getUnitPrice();
            }
        }

        if ($totalQty <= 0.0) {
            return null;
        }

        return (string) round($totalCost / $totalQty, 4);
    }

    /** Weighted average purchase price in space currency (with historical FX) */
    public function getAvgPriceInSpaceCurrency(): ?string
    {
        $totalQty = 0.0;
        $totalCost = 0.0;

        foreach ($this->entries as $entry) {
            if ($entry->getKind() === AssetEntryKindEnum::Buy) {
                $qty = (float) $entry->getQuantity();
                $totalQty += $qty;
                $totalCost += $qty * (float) $entry->getUnitPrice() * (float) $entry->getFxRate();
            }
        }

        if ($totalQty <= 0.0) {
            return null;
        }

        return (string) round($totalCost / $totalQty, 4);
    }

    /** Total cost basis in asset currency */
    public function getTotalCost(): float
    {
        $total = 0.0;
        foreach ($this->entries as $entry) {
            if ($entry->getKind() === AssetEntryKindEnum::Buy) {
                $total += (float) $entry->getQuantity() * (float) $entry->getUnitPrice();
            }
        }

        return $total;
    }

    /** Total cost basis in space currency (with historical FX) */
    public function getTotalCostInSpaceCurrency(): float
    {
        $total = 0.0;
        foreach ($this->entries as $entry) {
            if ($entry->getKind() === AssetEntryKindEnum::Buy) {
                $total += (float) $entry->getQuantity() * (float) $entry->getUnitPrice() * (float) $entry->getFxRate();
            }
        }

        return $total;
    }

    /** Total dividends received in space currency */
    public function getTotalDividends(): float
    {
        $total = 0.0;
        foreach ($this->entries as $entry) {
            if ($entry->getKind() === AssetEntryKindEnum::Dividend) {
                $total += (float) $entry->getQuantity() * (float) $entry->getUnitPrice() * (float) $entry->getFxRate();
            }
        }

        return $total;
    }

    /** Total fees paid across all entries in space currency */
    public function getTotalFees(): float
    {
        $total = 0.0;
        foreach ($this->entries as $entry) {
            $total += (float) $entry->getFees();
        }

        return $total;
    }

    /** Whether the asset has any buy entries (used to check if it's a tracked position) */
    public function hasPosition(): bool
    {
        return $this->getTotalQuantity() > 0.0;
    }

    /** All distinct accounts linked to this asset's entries. */
    public function getAccounts(): array
    {
        $accounts = [];
        foreach ($this->entries as $entry) {
            $account = $entry->getAccount();
            if ($account !== null && !in_array($account, $accounts, true)) {
                $accounts[] = $account;
            }
        }

        return $accounts;
    }

    /** The account of the first entry, used as the asset's primary display account. */
    public function getPrimaryAccount(): ?Account
    {
        $entries = $this->entries->toArray();
        if ($entries === []) {
            return null;
        }

        return $entries[0]->getAccount();
    }
}
