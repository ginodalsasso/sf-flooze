<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\RecurrenceFrequencyEnum;
use App\Enum\TransactionTypeEnum;
use App\Repository\RecurringTransactionRepository;
use App\Trait\SoftDeleteTrait;
use App\Trait\SpaceScopeTrait;
use App\Trait\TimestampTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RecurringTransactionRepository::class)]
#[ORM\Table(name: 'recurring_transaction')]
#[ORM\HasLifecycleCallbacks]
class RecurringTransaction
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

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(name: 'category_id', nullable: true)]
    private ?Category $category = null;

    /** @var Collection<int, Tag> */
    #[ORM\ManyToMany(targetEntity: Tag::class)]
    #[ORM\JoinTable(name: 'recurring_transaction_tag')]
    #[ORM\JoinColumn(name: 'recurring_transaction_id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'tag_id', onDelete: 'CASCADE')]
    private Collection $tags;

    #[ORM\Column(type: 'string', enumType: TransactionTypeEnum::class)]
    private TransactionTypeEnum $type;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    private string $amount;

    /** Recurrence name, reused as the description of every materialised Transaction. */
    #[ORM\Column(length: 255)]
    private string $label;

    #[ORM\Column(type: 'string', enumType: RecurrenceFrequencyEnum::class)]
    private RecurrenceFrequencyEnum $frequency;

    /** Frequency multiplier: "every 2 months" is MONTHLY + 2. */
    #[ORM\Column(type: 'smallint', options: ['default' => 1])]
    private int $intervalCount = 1;

    /** Anchor of every occurrence: no date is ever derived from the previous one. */
    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $startDate;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $endDate = null;

    /** Cursor: next occurrence left untreated, neither confirmed nor skipped. */
    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $nextOccurrenceDate;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    public function __construct()
    {
        $this->tags = new ArrayCollection();
    }

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

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    /** @return Collection<int, Tag> */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        $this->tags->removeElement($tag);

        return $this;
    }

    /** @param Tag[] $tags */
    public function replaceTags(array $tags): static
    {
        $this->tags->clear();

        foreach ($tags as $tag) {
            $this->addTag($tag);
        }

        return $this;
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

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getFrequency(): RecurrenceFrequencyEnum
    {
        return $this->frequency;
    }

    public function setFrequency(RecurrenceFrequencyEnum $frequency): static
    {
        $this->frequency = $frequency;

        return $this;
    }

    public function getIntervalCount(): int
    {
        return $this->intervalCount;
    }

    public function setIntervalCount(int $intervalCount): static
    {
        $this->intervalCount = $intervalCount;

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

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getNextOccurrenceDate(): \DateTimeImmutable
    {
        return $this->nextOccurrenceDate;
    }

    public function setNextOccurrenceDate(\DateTimeImmutable $nextOccurrenceDate): static
    {
        $this->nextOccurrenceDate = $nextOccurrenceDate;

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
