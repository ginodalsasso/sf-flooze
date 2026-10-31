<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\RentPaymentStatusEnum;
use App\Repository\RentPaymentRepository;
use App\Trait\SpaceScopeTrait;
use App\Trait\TimestampTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RentPaymentRepository::class)]
#[ORM\Table(name: 'rent_payment')]
#[ORM\UniqueConstraint(name: 'uniq_rent_payment_lease_due_date', columns: ['lease_id', 'due_date'])]
#[ORM\HasLifecycleCallbacks]
class RentPayment
{
    use TimestampTrait;
    use SpaceScopeTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Lease::class)]
    #[ORM\JoinColumn(name: 'lease_id', nullable: false)]
    private Lease $lease;

    /** Set only once the payment is confirmed — a due date alone never touches the ledger. */
    #[ORM\ManyToOne(targetEntity: Transaction::class)]
    #[ORM\JoinColumn(name: 'transaction_id', nullable: true, unique: true)]
    private ?Transaction $transaction = null;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    private string $amount = '0.00';

    #[ORM\Column(name: 'due_date', type: 'date_immutable')]
    private \DateTimeImmutable $dueDate;

    #[ORM\Column(name: 'paid_date', type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $paidDate = null;

    #[ORM\Column(type: 'string', enumType: RentPaymentStatusEnum::class)]
    private RentPaymentStatusEnum $status;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLease(): Lease
    {
        return $this->lease;
    }

    public function setLease(Lease $lease): static
    {
        $this->lease = $lease;

        return $this;
    }

    public function getTransaction(): ?Transaction
    {
        return $this->transaction;
    }

    public function setTransaction(?Transaction $transaction): static
    {
        $this->transaction = $transaction;

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

    public function getDueDate(): \DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function setDueDate(\DateTimeImmutable $dueDate): static
    {
        $this->dueDate = $dueDate;

        return $this;
    }

    public function getPaidDate(): ?\DateTimeImmutable
    {
        return $this->paidDate;
    }

    public function setPaidDate(?\DateTimeImmutable $paidDate): static
    {
        $this->paidDate = $paidDate;

        return $this;
    }

    public function getStatus(): RentPaymentStatusEnum
    {
        return $this->status;
    }

    public function setStatus(RentPaymentStatusEnum $status): static
    {
        $this->status = $status;

        return $this;
    }
}
