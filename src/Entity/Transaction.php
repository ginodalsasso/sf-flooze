<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AssetEntryKindEnum;
use App\Enum\TransactionTypeEnum;
use App\Repository\TransactionRepository;
use App\Trait\SoftDeleteTrait;
use App\Trait\SpaceScopeTrait;
use App\Trait\TimestampTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
#[ORM\Table(name: 'transaction')]
#[ORM\HasLifecycleCallbacks]
class Transaction
{
    use TimestampTrait;
    use SpaceScopeTrait;
    use SoftDeleteTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(name: 'account_id', nullable: false)]
    private Account $account;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(name: 'destination_account_id', nullable: true)]
    private ?Account $destinationAccount = null;

    #[ORM\ManyToOne(targetEntity: AssetEntry::class, inversedBy: 'transactions')]
    #[ORM\JoinColumn(name: 'asset_entry_id', nullable: true, onDelete: 'SET NULL')]
    private ?AssetEntry $assetEntry = null;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(name: 'category_id', nullable: true)]
    private ?Category $category = null;

    #[ORM\Column(type: 'string', enumType: TransactionTypeEnum::class)]
    private TransactionTypeEnum $type;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    private string $amount;

    /** Account currency → space currency, frozen at transaction date: a later rate must never rewrite the past. */
    #[ORM\Column(type: 'decimal', precision: 15, scale: 6, options: ['default' => '1.000000'])]
    private string $fxRate = '1.000000';

    /** Amount actually credited to the destination account, in its own currency. Null when both accounts share a currency. */
    #[ORM\Column(type: 'decimal', precision: 15, scale: 2, nullable: true)]
    private ?string $destinationAmount = null;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $date;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAccount(): Account
    {
        return $this->account;
    }

    public function setAccount(Account $account): static
    {
        $this->account = $account;

        return $this;
    }

    public function getDestinationAccount(): ?Account
    {
        return $this->destinationAccount;
    }

    public function setDestinationAccount(?Account $destinationAccount): static
    {
        $this->destinationAccount = $destinationAccount;

        return $this;
    }

    public function getAssetEntry(): ?AssetEntry
    {
        return $this->assetEntry;
    }

    public function setAssetEntry(?AssetEntry $assetEntry): static
    {
        $this->assetEntry = $assetEntry;

        return $this;
    }

    public function isLinkedToAsset(): bool
    {
        return $this->assetEntry !== null;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    /**
     * Returns the category name to display in transaction lists.
     * Falls back to a generic asset label when the transaction was generated
     * from an AssetEntry and no manual category was set.
     */
    public function getCategoryLabel(): ?string
    {
        if ($this->category !== null) {
            return $this->category->getName();
        }

        if ($this->assetEntry !== null) {
            return match ($this->assetEntry->getKind()) {
                AssetEntryKindEnum::BUY => 'Investissement',
                AssetEntryKindEnum::SELL => 'Vente d\'actif',
                AssetEntryKindEnum::DIVIDEND => 'Dividende',
            };
        }

        return null;
    }

    public function getType(): TransactionTypeEnum
    {
        return $this->type;
    }

    public function setType(TransactionTypeEnum $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;

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

    public function getDestinationAmount(): ?string
    {
        return $this->destinationAmount;
    }

    public function setDestinationAmount(?string $destinationAmount): static
    {
        $this->destinationAmount = $destinationAmount;

        return $this;
    }

    /** Amount converted to the space currency: amount × fx_rate. */
    public function getAmountInSpaceCurrency(): string
    {
        return bcmul($this->amount, $this->fxRate, 2);
    }

    /** Amount credited to the destination account, in that account's currency. */
    public function getCreditedAmount(): string
    {
        return $this->destinationAmount ?? $this->amount;
    }

    /** True when $account receives the money — i.e. it is the destination of a transfer. */
    public function isIncomingFor(Account $account): bool
    {
        if (!$this->isTransfer() || $this->destinationAccount === null) {
            return false;
        }

        return $this->destinationAccount->getId() === $account->getId();
    }

    /** Only a transfer credits a destination account: on any other type the field is meaningless. */
    public function isTransfer(): bool
    {
        return $this->type === TransactionTypeEnum::TRANSFER;
    }

    /** Amount as it hits $account, expressed in that account's currency. */
    public function getAmountFor(Account $account): string
    {
        if ($this->isIncomingFor($account)) {
            return $this->getCreditedAmount();
        }

        return $this->amount;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }
}
