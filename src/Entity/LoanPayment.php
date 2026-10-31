<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\LoanPaymentRepository;
use App\Trait\SpaceScopeTrait;
use App\Trait\TimestampTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * One instalment of a loan, recorded only once paid — the schedule itself is computed, never stored.
 */
#[ORM\Entity(repositoryClass: LoanPaymentRepository::class)]
#[ORM\Table(name: 'loan_payment')]
#[ORM\UniqueConstraint(name: 'uniq_loan_payment_loan_month', columns: ['loan_id', 'month_number'])]
#[ORM\HasLifecycleCallbacks]
class LoanPayment
{
    use TimestampTrait;
    use SpaceScopeTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Loan::class)]
    #[ORM\JoinColumn(name: 'loan_id', nullable: false)]
    private Loan $loan;

    /** Set by the confirmation that books the instalment — the ledger entry it produced. */
    #[ORM\ManyToOne(targetEntity: Transaction::class)]
    #[ORM\JoinColumn(name: 'transaction_id', nullable: true, unique: true)]
    private ?Transaction $transaction = null;

    #[ORM\Column(name: 'month_number', type: 'integer')]
    private int $monthNumber;

    #[ORM\Column(name: 'due_date', type: 'date_immutable')]
    private \DateTimeImmutable $dueDate;

    #[ORM\Column(name: 'capital_part', type: 'decimal', precision: 15, scale: 2)]
    private string $capitalPart;

    #[ORM\Column(name: 'interest_part', type: 'decimal', precision: 15, scale: 2)]
    private string $interestPart;

    #[ORM\Column(name: 'insurance_part', type: 'decimal', precision: 15, scale: 2)]
    private string $insurancePart;

    #[ORM\Column(name: 'remaining_capital', type: 'decimal', precision: 15, scale: 2)]
    private string $remainingCapital;

    #[ORM\Column(name: 'paid_date', type: 'date_immutable')]
    private \DateTimeImmutable $paidDate;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLoan(): Loan
    {
        return $this->loan;
    }

    public function setLoan(Loan $loan): static
    {
        $this->loan = $loan;

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

    public function getMonthNumber(): int
    {
        return $this->monthNumber;
    }

    public function setMonthNumber(int $monthNumber): static
    {
        $this->monthNumber = $monthNumber;

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

    public function getCapitalPart(): string
    {
        return $this->capitalPart;
    }

    public function setCapitalPart(string $capitalPart): static
    {
        $this->capitalPart = $capitalPart;

        return $this;
    }

    public function getInterestPart(): string
    {
        return $this->interestPart;
    }

    public function setInterestPart(string $interestPart): static
    {
        $this->interestPart = $interestPart;

        return $this;
    }

    public function getInsurancePart(): string
    {
        return $this->insurancePart;
    }

    public function setInsurancePart(string $insurancePart): static
    {
        $this->insurancePart = $insurancePart;

        return $this;
    }

    public function getRemainingCapital(): string
    {
        return $this->remainingCapital;
    }

    public function setRemainingCapital(string $remainingCapital): static
    {
        $this->remainingCapital = $remainingCapital;

        return $this;
    }

    public function getPaidDate(): \DateTimeImmutable
    {
        return $this->paidDate;
    }

    public function setPaidDate(\DateTimeImmutable $paidDate): static
    {
        $this->paidDate = $paidDate;

        return $this;
    }
}
