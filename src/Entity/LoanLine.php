<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\LoanLineRepository;
use App\Trait\SpaceScopeTrait;
use App\Trait\TimestampTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * One line of a loan: its own capital, rate, insurance rate and duration.
 *
 * A French mortgage is routinely made of several lines released together — main loan, PTZ,
 * employer loan — each amortised on its own terms. The rate belongs to the line, never to
 * the file, so a single rate on the file could only ever be an approximation.
 */
#[ORM\Entity(repositoryClass: LoanLineRepository::class)]
#[ORM\Table(name: 'loan_line')]
#[ORM\HasLifecycleCallbacks]
class LoanLine
{
    use TimestampTrait;
    use SpaceScopeTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Loan::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(name: 'loan_id', nullable: false, onDelete: 'CASCADE')]
    private Loan $loan;

    #[ORM\Column(length: 100)]
    private string $label;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    private string $amount = '0.00';

    /** Annual nominal rate as a percentage: 1.1000 means 1.10 %. */
    #[ORM\Column(type: 'decimal', precision: 6, scale: 4)]
    private string $rate = '0.0000';

    /** Annual insurance rate as a percentage, applied to this line's capital. */
    #[ORM\Column(name: 'insurance_rate', type: 'decimal', precision: 6, scale: 4)]
    private string $insuranceRate = '0.0000';

    #[ORM\Column(name: 'duration_months', type: 'smallint')]
    private int $durationMonths = 0;

    /**
     * Capital + interest only (never insurance), when the bank smoothed this line against
     * the file's other lines. Overrides the annuity formula: the schedule still derives
     * interest from the remaining capital, but the instalment itself is this fixed figure
     * instead of being computed from capital/rate/duration.
     */
    #[ORM\Column(name: 'negotiated_monthly_payment', type: 'decimal', precision: 15, scale: 2, nullable: true)]
    private ?string $negotiatedMonthlyPayment = null;

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

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

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

    public function getRate(): string
    {
        return $this->rate;
    }

    public function setRate(string $rate): static
    {
        $this->rate = $rate;

        return $this;
    }

    public function getInsuranceRate(): string
    {
        return $this->insuranceRate;
    }

    public function setInsuranceRate(string $insuranceRate): static
    {
        $this->insuranceRate = $insuranceRate;

        return $this;
    }

    public function getDurationMonths(): int
    {
        return $this->durationMonths;
    }

    public function setDurationMonths(int $durationMonths): static
    {
        $this->durationMonths = $durationMonths;

        return $this;
    }

    public function getNegotiatedMonthlyPayment(): ?string
    {
        return $this->negotiatedMonthlyPayment;
    }

    public function setNegotiatedMonthlyPayment(?string $negotiatedMonthlyPayment): static
    {
        $this->negotiatedMonthlyPayment = $negotiatedMonthlyPayment;

        return $this;
    }
}
